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
        $author = User::query()->oldest('id')->first();

        // Front-end language is German, so demo content is German too.
        $categories = [
            'Politik'      => 'Aktuelle Politik-Nachrichten und Analysen.',
            'Sport'        => 'Fußball, Sport und alles aus der Welt des Sports.',
            'Wirtschaft'   => 'Märkte, Wirtschaft und Unternehmensnachrichten.',
            'Technik'      => 'Gadgets, Apps und die Tech-Branche.',
            'Unterhaltung' => 'Filme, Musik und Promi-News.',
            'Welt'         => 'Internationale Nachrichten aus aller Welt.',
        ];

        $tags = collect(['Berlin', 'Wahl', 'Fußball', 'Startup', 'KI', 'Kultur', 'Wirtschaft', 'Gesundheit'])
            ->map(fn ($name) => Tag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]));

        $sort = 1;
        foreach ($categories as $name => $desc) {
            $category = Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'description' => $desc, 'sort_order' => $sort++, 'is_active' => true]
            );

            for ($i = 1; $i <= 5; $i++) {
                $title = "{$name}: Beispielmeldung Nummer {$i}";
                $article = Article::firstOrCreate(
                    ['slug' => Str::slug($title)],
                    [
                        'title'        => $title,
                        'subtitle'     => "Eine kurze Unterzeile für die Rubrik {$name}.",
                        'excerpt'      => "Eine Beispielmeldung aus der Rubrik {$name}, die nur das Layout der Seite zeigt.",
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
        $p = "<p>Dies ist ein Beispielartikel aus der Rubrik {$name}. Der Text dient nur dazu, Layout, Typografie und Abstände der Artikelseite zu zeigen.</p>";

        return $p
            . '<h2>Hintergrund</h2><p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>'
            . '<p>Ut enim ad minim veniam, quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat.</p>'
            . '<blockquote>Ein Beispielzitat, wie es innerhalb eines Artikels dargestellt wird.</blockquote>'
            . '<h2>Wie geht es weiter?</h2><p>Duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur.</p>';
    }
}
