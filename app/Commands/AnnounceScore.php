<?php

namespace App\Commands;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\DB;

class AnnounceScore extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'announce:score';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Announce score change';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $client = new Client(['base_uri' => env('WORLDCUP_API')]);
        $uri = 'matches';
        $res = $client->request('GET', $uri);
        $data = json_decode($res->getBody(), true);

        foreach ($data as $key => $row) {
            // If the match not live skip
            if (!in_array($row['status'], ['future', 'completed'])) {
                continue;
            }
            $this->info("Live match : {$row['home_team']['country']} - {$row['away_team']['country']}");
            $match = DB::table('matches')->where('fifa_id', $row['fifa_id'])->first();

            $liveMatchLastScoreUpdatedAt = Carbon::parse($row['last_score_update_at']);
            $matchLastScoreUpdatedAt = Carbon::parse($match->last_score_update_at);

            if ($liveMatchLastScoreUpdatedAt->greaterThan($matchLastScoreUpdatedAt)) {
                $message = "{$row['home_team']['country']} {$row['home_team']['goals']} - {$row['away_team']['goals']} {$row['away_team']['country']}";
                $this->postToSlack($message);
            }
        }

    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     */
    public function schedule(Schedule $schedule): void
    {
        $schedule->command(static::class)->everyMinute();
    }


    protected function postToSlack($message)
    {
        $client = new Client();
        $webhook = env('SLACK_WEBOOK_URL');

        if(is_null($webhook)){
            return false;
        }

        $data = [
            'channel' => env('SLACK_CHANNEL'),
            'icon_emoji' => ':soccer:',
            'text' => $message
        ];

        $client->post($webhook,[
            'headers' => [
                'content-type' => 'application/json'
            ],
            'body' => json_encode($data)
        ]);

        return true;
    }
}
