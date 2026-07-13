<?php

namespace Database\Seeders;

use App\Models\Article;
use App\Models\Category;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoContentSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::where('email', 'admin@newspaper.test')->first();

        $categories = [
            'Politics'      => 'Latest political news and analysis.',
            'Sports'        => 'Cricket, football aur baaki khel ki khabrein.',
            'Business'      => 'Markets, economy aur business updates.',
            'Technology'    => 'Gadgets, apps aur tech industry.',
            'Entertainment' => 'Movies, music aur celebrity news.',
            'World'         => 'International news from around the globe.',
        ];

        $tags = collect(['India', 'Election', 'Cricket', 'Startup', 'AI', 'Bollywood', 'Economy', 'Health'])
            ->map(fn ($name) => Tag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]));

        $sort = 1;
        foreach ($categories as $name => $desc) {
            $category = Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $desc, 'sort_order' => $sort++, 'is_active' => true]
            );

            for ($i = 1; $i <= 5; $i++) {
                $title = "{$name} sample news story number {$i}";
                $article = Article::firstOrCreate(
                    ['slug' => Str::slug($title)],
                    [
                        'title'        => $title,
                        'subtitle'     => "Ek chhota sa subtitle {$name} ke story ke liye.",
                        'excerpt'      => "Yeh {$name} category ki ek demo news story hai jo layout dikhane ke liye banayi gayi hai.",
                        'body'         => $this->body($name),
                        'category_id'  => $category->id,
                        'user_id'      => $author->id,
                        'status'       => 'published',
                        'is_featured'  => $i === 1,
                        'is_breaking'  => ($name === 'Politics' && $i <= 2),
                        'views'        => random_int(50, 5000),
                        'published_at' => now()->subDays(random_int(0, 20))->subHours(random_int(0, 23)),
                    ]
                );

                $article->tags()->syncWithoutDetaching($tags->random(random_int(1, 3))->pluck('id'));
            }
        }
    }

    private function body(string $name): string
    {
        $p = "<p>Yeh {$name} category ki ek demo article hai. Is paragraph me sirf placeholder text hai taaki aap article page ka layout, typography aur spacing dekh sakein.</p>";

        return $p
            . "<h2>Background</h2><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>"
            . "<p>Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>"
            . "<blockquote>Yeh ek example quote hai jo article ke andar dikhaya gaya hai.</blockquote>"
            . "<h2>Aage kya?</h2><p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</p>";
    }
}
