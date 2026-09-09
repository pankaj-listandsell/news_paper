<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Category;
use App\Models\Setting;
use App\Models\SocialAccount;
use App\Models\SocialShare;
use App\Models\User;
use App\Social\FacebookPublisher;
use App\Social\LinkedInPublisher;
use App\Social\SocialMessage;
use App\Social\XPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SocialPublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // No test may reach the internet.
        Http::preventStrayRequests();
    }

    /* ------------------------------ drivers ------------------------------ */

    public function test_facebook_posts_the_message_and_reads_back_the_post_url(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['id' => '99887766_1234567890']),
        ]);

        $account = $this->account('facebook', [
            'page_id' => '99887766', 'access_token' => 'tok',
        ]);
        $article = $this->article();

        $result = (new FacebookPublisher())->publish($account, $article, SocialMessage::for($article, 'facebook'));

        $this->assertTrue($result->ok);
        $this->assertSame('99887766_1234567890', $result->remoteId);
        $this->assertSame('https://www.facebook.com/99887766/posts/1234567890', $result->remoteUrl);

        Http::assertSent(function ($request) use ($article) {
            return str_contains($request->url(), '/99887766/feed')
                && str_contains($request['message'], $article->title)
                && $request['access_token'] === 'tok';
        });
    }

    public function test_facebook_reports_the_reason_it_refused(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response([
                'error' => ['type' => 'OAuthException', 'message' => 'Invalid OAuth access token.'],
            ], 400),
        ]);

        $account = $this->account('facebook', ['page_id' => '1', 'access_token' => 'bad']);
        $article = $this->article();

        $result = (new FacebookPublisher())->publish($account, $article, SocialMessage::for($article, 'facebook'));

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('Invalid OAuth access token', $result->error);
    }

    public function test_facebook_says_what_is_missing(): void
    {
        $account = $this->account('facebook', ['page_id' => '1']);

        $publisher = new FacebookPublisher();

        $this->assertFalse($publisher->isConfigured($account));
        $this->assertSame(['page access token'], $publisher->missing($account));
    }

    public function test_linkedin_posts_as_the_organisation(): void
    {
        Http::fake([
            'api.linkedin.com/*' => Http::response([], 201, ['x-restli-id' => 'urn:li:share:7123']),
        ]);

        $account = $this->account('linkedin', [
            'organization_id' => '5566', 'access_token' => 'tok',
        ]);
        $article = $this->article();

        $result = (new LinkedInPublisher())->publish($account, $article, SocialMessage::for($article, 'linkedin'));

        $this->assertTrue($result->ok);
        $this->assertSame('https://www.linkedin.com/feed/update/urn:li:share:7123', $result->remoteUrl);

        Http::assertSent(fn ($request) => $request['author'] === 'urn:li:organization:5566'
            && $request['visibility'] === 'PUBLIC'
            && $request->hasHeader('LinkedIn-Version'));
    }

    public function test_linkedin_explains_a_missing_permission(): void
    {
        Http::fake(['api.linkedin.com/*' => Http::response([], 403)]);

        $account = $this->account('linkedin', ['organization_id' => '1', 'access_token' => 'tok']);
        $article = $this->article();

        $result = (new LinkedInPublisher())->publish($account, $article, SocialMessage::for($article, 'linkedin'));

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('w_organization_social', $result->error);
    }

    public function test_x_signs_the_request_and_posts_the_text(): void
    {
        Http::fake([
            'api.twitter.com/*' => Http::response(['data' => ['id' => '1800000000000000000']]),
        ]);

        $account = $this->account('x', [
            'api_key' => 'ck', 'api_secret' => 'cs',
            'access_token' => 'at', 'access_token_secret' => 'ats',
        ]);
        $article = $this->article();

        $result = (new XPublisher())->publish($account, $article, SocialMessage::for($article, 'x'));

        $this->assertTrue($result->ok);
        $this->assertSame('https://x.com/i/web/status/1800000000000000000', $result->remoteUrl);

        Http::assertSent(function ($request) {
            $auth = $request->header('Authorization')[0] ?? '';

            return str_starts_with($auth, 'OAuth ')
                && str_contains($auth, 'oauth_consumer_key="ck"')
                && str_contains($auth, 'oauth_token="at"')
                && str_contains($auth, 'oauth_signature_method="HMAC-SHA1"')
                && str_contains($auth, 'oauth_signature="');
        });
    }

    public function test_x_reports_a_rate_limit_as_worth_retrying(): void
    {
        Http::fake(['api.twitter.com/*' => Http::response([], 429)]);

        $account = $this->account('x', [
            'api_key' => 'ck', 'api_secret' => 'cs',
            'access_token' => 'at', 'access_token_secret' => 'ats',
        ]);
        $article = $this->article();

        $result = (new XPublisher())->publish($account, $article, SocialMessage::for($article, 'x'));

        $this->assertFalse($result->ok);
        $this->assertStringContainsString('rate limit', strtolower($result->error));
    }

    public function test_credentials_are_not_stored_in_the_clear(): void
    {
        $account = $this->account('facebook', ['access_token' => 'super-secret-token']);

        $raw = \DB::table('social_accounts')->where('id', $account->id)->value('credentials');

        $this->assertStringNotContainsString('super-secret-token', $raw);
        $this->assertSame('super-secret-token', $account->fresh()->credential('access_token'));
    }

    /* ------------------------------ command ------------------------------ */

    public function test_the_scheduled_run_posts_what_is_waiting(): void
    {
        Setting::set('social_practice_mode', '1');
        Setting::set('social_batch_size', '2');
        $this->account('facebook', [], ['auto_post' => true]);

        $oldest = $this->article(['published_at' => now()->subDays(3)]);
        $middle = $this->article(['published_at' => now()->subDays(2)]);
        $newest = $this->article(['published_at' => now()->subDay()]);

        $this->artisan('social:publish')->assertSuccessful();

        // Batch size caps the run, and the oldest go first so nothing is left
        // behind while newer stories keep jumping the queue.
        $this->assertSame(2, SocialShare::sent()->count());
        $this->assertEqualsCanonicalizing(
            [$oldest->id, $middle->id],
            SocialShare::sent()->pluck('article_id')->all()
        );
        $this->assertSame(0, SocialShare::where('article_id', $newest->id)->count());
    }

    public function test_the_scheduled_run_never_repeats_itself(): void
    {
        Setting::set('social_practice_mode', '1');
        $this->account('facebook', [], ['auto_post' => true]);
        $this->article();

        $this->artisan('social:publish')->assertSuccessful();
        $this->artisan('social:publish')->assertSuccessful();

        $this->assertSame(1, SocialShare::count());
        $this->assertSame(1, SocialShare::first()->attempts);
    }

    public function test_the_backlog_cutoff_protects_older_articles(): void
    {
        Setting::set('social_practice_mode', '1');
        Setting::set('social_backlog_cutoff', now()->subDays(7)->toDateString());
        $this->account('facebook', [], ['auto_post' => true]);

        $old = $this->article(['published_at' => now()->subMonths(6)]);
        $new = $this->article(['published_at' => now()->subDay()]);

        $this->artisan('social:publish')->assertSuccessful();

        $this->assertSame(1, SocialShare::count());
        $this->assertSame($new->id, SocialShare::first()->article_id);
    }

    public function test_an_account_left_out_of_the_schedule_is_not_posted_to(): void
    {
        Setting::set('social_practice_mode', '1');
        $this->account('facebook', [], ['auto_post' => false]);
        $this->article();

        $this->artisan('social:publish')->assertSuccessful();

        $this->assertSame(0, SocialShare::count());
    }

    public function test_a_dry_run_posts_nothing(): void
    {
        Setting::set('social_practice_mode', '1');
        $this->account('facebook', [], ['auto_post' => true]);
        $this->article();

        $this->artisan('social:publish', ['--dry-run' => true])->assertSuccessful();

        $this->assertSame(0, SocialShare::count());
    }

    public function test_frozen_and_redirected_articles_are_never_offered(): void
    {
        Setting::set('social_practice_mode', '1');
        $this->account('facebook', [], ['auto_post' => true]);

        $this->article(['http_status' => 304]);
        $this->article(['http_status' => 301, 'redirect_url' => '/x']);
        $normal = $this->article();

        $this->artisan('social:publish')->assertSuccessful();

        $this->assertSame(1, SocialShare::count());
        $this->assertSame($normal->id, SocialShare::first()->article_id);
    }

    /* ------------------------------------------------------------------- */

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

    private function account(string $platform, array $credentials = [], array $attributes = []): SocialAccount
    {
        $account = new SocialAccount(array_merge([
            'platform'  => $platform,
            'name'      => 'Hauptstadt Report',
            'is_active' => true,
        ], $attributes));

        $account->setCredentials($credentials);
        $account->save();

        return $account;
    }
}
