<?php

namespace App\Console\Commands;

use App\Topic;
use Goutte\Client as Goutte;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\DomCrawler\Crawler;

class GetTopicsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'topics:get';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scrap topics from github.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $seconds = 3600;

        $goutte = new Goutte(['headers' => ['accept' => 'text/html']]);
        $guzzle = new Guzzle(['timeout' => 60]);
        $goutte->setClient($guzzle);

        $crawler = Cache::remember('topics', $seconds, function () use ($goutte) {
            return $goutte->request('GET', $this->getUri())->html();
        });
        $crawler = new Crawler($crawler);
        $this->retrieveFields($crawler);

        $after = $crawler->filter('input[name="after"]')->attr('value');
        if ($after) {
            $crawler = Cache::remember('topics_2', $seconds, function () use ($goutte, $after) {
                return $goutte->request('GET', $this->getUri(['after' => $after]))->html();
            });
            $crawler = new Crawler($crawler);

            $this->retrieveFields($crawler);
        }
    }

    private function getUri(? array $queries = []): string
    {
        $uri = "https://github.com/topics";
        $queries = array_merge($queries, ['utf8' => '✓']);

        if ($queries) {
            $uri = $uri . '?';

            foreach (array_keys($queries) as $query) {
                $uri .= $query . '=' . $queries[$query] . '&';
            }

            $uri = substr($uri, 0, -1);
        }

        return $uri;
    }

    private function retrieveFields(Crawler $crawler): void
    {
        $crawler->filter('li.py-4.border-bottom')->each(function ($node) {
            $name = $node->filter('p.f3')->each(function ($node) {
                return $node->text();
            });
            $name = count($name) > 0 ? $name[0] : null;

            $link_to_github = $node->filter('a.d-flex.no-underline')->each(function ($node) {
                return "https://github.com{$node->attr('href')}";
            });
            if (!$link_to_github) {
                $this->info("No link to Github found for topic \"{$name}\".");
            }
            $link_to_github = count($link_to_github) > 0 ? $link_to_github[0] : null;

            $description = $node->filter('p.f5')->each(function ($node) {
                return $node->text();
            });
            if (!$description) {
                $this->info("No description found for topic \"{$name}\".");
            }
            $description = count($description) > 0 ? $description[0] : null;

            $image = $node->filter('img')->each(function ($node) {
                return $node->attr('src');
            });
            if (!$image) {
                $this->info("No image found for topic \"{$name}\".");
            }
            $image = count($image) > 0 ? $image[0] : null;


            $data = [
                'name' => $name,
                'link_to_github' => $link_to_github,
                'description' => $description,
                'image' => $image,
            ];

            $this->createTopic($data);
        });
    }

    private function createTopic(array $topic): void
    {
        if (!Topic::where('name', $topic['name'])->exists()) {
            Topic::create($topic);
        }
    }
}
