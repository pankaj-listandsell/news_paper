<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\SocialShare;
use App\Models\User;
use App\Social\PublisherFactory;
use App\Social\SocialMessage;
use App\Social\SocialPublisher;
use App\Social\SocialResult;
use App\Social\SocialSharer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialSharerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Nothing may leave the machine during a test run.
        Setting::set('social_practice_mode', '1');
    }

    public function test_it_posts_an_article_and_writes_it_down(): void
    {
        $article = $this->article();
        $this->account('facebook');

        $share = (new SocialSharer())->share($article, 'facebook');

        $this->assertSame(SocialShare::SENT, $share->status);
        $this->assertNotNull($share->remote_id);
        $this->assertNotNull($share->posted_at);
        $this->assertSame(1, $share->attempts);
    }

    public function test_the_same_article_never_goes_out_twice(): void
    {
        $article = $this->article();
        $this->account('facebook');

        $sharer = new SocialSharer();
        $first  = $sharer->share($article, 'facebook');
        $second = $sharer->share($article, 'facebook');

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, $second->attempts, 'a second call must not post again');
        $this->assertSame(1, SocialShare::count());
    }

    public function test_an_article_can_go_to_several_platforms(): void
    {
        $article = $this->article();
        $this->account('facebook');
        $this->account('x');

        $sharer = new SocialSharer();
        $sharer->share($article, 'facebook');
        $sharer->share($article, 'x');

        $this->assertSame(2, SocialShare::sent()->count());
    }

    public function test_an_unpublished_article_is_held_back(): void
    {
        $article = $this->article(['status' => 'draft']);
        $this->account('facebook');

        $share = (new SocialSharer())->share($article, 'facebook');

        $this->assertSame(SocialShare::PENDING, $share->status);
        $this->assertStringContainsString('not published', $share->error);
    }

    public function test_a_frozen_or_redirected_article_is_held_back(): void
    {
        $this->account('facebook');
        $sharer = new SocialSharer();

        foreach ([301, 304] as $status) {
            $share = $sharer->share($this->article(['http_status' => $status]), 'facebook');

            $this->assertSame(SocialShare::PENDING, $share->status);
            $this->assertStringContainsString((string) $status, $share->error);
        }
    }

    public function test_a_switched_off_account_is_held_back(): void
    {
        $article = $this->article();
        $this->account('facebook', ['is_active' => false]);

        $share = (new SocialSharer())->share($article, 'facebook');

        $this->assertSame(SocialShare::PENDING, $share->status);
        $this->assertStringContainsString('switched off', $share->error);
    }

    public function test_an_expired_token_is_held_back(): void
    {
        $article = $this->article();
        $this->account('facebook', ['token_expires_at' => now()->subDay()]);

        $share = (new SocialSharer())->share($article, 'facebook');

        $this->assertSame(SocialShare::PENDING, $share->status);
        $this->assertStringContainsString('expired', $share->error);
    }

    public function test_a_failure_is_retried_and_then_given_up_on(): void
    {
        $article = $this->article();
        $this->account('facebook');

        $sharer = new SocialSharer($this->failingFactory('Rate limit reached'));

        $share = $sharer->share($article, 'facebook');
        $this->assertSame(SocialShare::PENDING, $share->status, 'first failure should be retried');
        $this->assertSame('Rate limit reached', $share->error);

        $sharer->share($article, 'facebook');
        $share = $sharer->share($article, 'facebook')->fresh();

        $this->assertSame(SocialShare::FAILED, $share->status);
        $this->assertSame(3, $share->attempts);
    }

    public function test_a_driver_that_throws_does_not_take_the_run_down(): void
    {
        $article = $this->article();
        $this->account('facebook');

        $share = (new SocialSharer($this->throwingFactory()))->share($article, 'facebook');

        $this->assertSame(SocialShare::PENDING, $share->status);
        $this->assertStringContainsString('boom', $share->error);
    }

    public function test_a_skipped_article_is_left_alone(): void
    {
        $article = $this->article();
        $this->account('facebook');

        SocialShare::create([
            'article_id' => $article->id,
            'platform'   => 'facebook',
            'status'     => SocialShare::SKIPPED,
        ]);

        $share = (new SocialSharer())->share($article, 'facebook');

        $this->assertSame(SocialShare::SKIPPED, $share->status);
        $this->assertSame(0, $share->attempts);
    }

    public function test_the_message_fits_inside_the_x_limit(): void
    {
        $article = $this->article([
            'title'   => str_repeat('Sehr langer Nachrichtentitel ', 20),
            'excerpt' => str_repeat('Und ein langer Vorspann. ', 20),
        ]);

        $message = SocialMessage::for($article, 'x');

        $this->assertLessThanOrEqual(280, mb_strlen($message->text));
        $this->assertStringContainsString($article->slug, $message->text);
    }

    public function test_a_roomier_platform_gets_the_standfirst_too(): void
    {
        $article = $this->article(['title' => 'Kurzer Titel', 'excerpt' => 'Ein kurzer Vorspann.']);

        $this->assertStringContainsString(
            'Ein kurzer Vorspann.',
            SocialMessage::for($article, 'facebook')->text
        );
    }

    /* ------------------------------------------------------------------ */

    private function article(array $attributes = []): Article
    {
        $category = Category::firstOrCreate(['slug' => 'politik'], ['name' => 'Politik']);

        return Article::create(array_merge([
            'title'        => 'Bundestag beschliesst neues Gesetz',
            'slug'         => 'artikel-' . uniqid(),
            'excerpt'      => 'Kurze Zusammenfassung.',
            'body'         => '<p>Inhalt</p>',
            'category_id'  => $category->id,
            'user_id'      => User::factory()->create()->id,
            'status'       => 'published',
            'published_at' => now()->subHour(),
        ], $attributes));
    }

    private function account(string $platform, array $attributes = []): SocialAccount
    {
        return SocialAccount::create(array_merge([
            'platform'  => $platform,
            'name'      => 'Hauptstadt Report',
            'is_active' => true,
        ], $attributes));
    }

    private function failingFactory(string $error): PublisherFactory
    {
        return new class($error) extends PublisherFactory
        {
            public function __construct(private string $error)
            {
            }

            public function for(string $platform): ?SocialPublisher
            {
                return new class($this->error) implements SocialPublisher
                {
                    public function __construct(private string $error)
                    {
                    }

                    public function isConfigured(SocialAccount $account): bool
                    {
                        return true;
                    }

                    public function missing(SocialAccount $account): array
                    {
                        return [];
                    }

                    public function publish(SocialAccount $account, Article $article, SocialMessage $message): SocialResult
                    {
                        return SocialResult::failed($this->error);
                    }
                };
            }
        };
    }

    private function throwingFactory(): PublisherFactory
    {
        return new class extends PublisherFactory
        {
            public function for(string $platform): ?SocialPublisher
            {
                return new class implements SocialPublisher
                {
                    public function isConfigured(SocialAccount $account): bool
                    {
                        return true;
                    }

                    public function missing(SocialAccount $account): array
                    {
                        return [];
                    }

                    public function publish(SocialAccount $account, Article $article, SocialMessage $message): SocialResult
                    {
                        throw new \RuntimeException('boom');
                    }
                };
            }
        };
    }
}
