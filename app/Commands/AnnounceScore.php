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
            if (in_array($row['status'], ['future', 'completed'])) {
                continue;
            }
            $match = DB::table('matches')->where('fifa_id', $row['fifa_id'])->first();
            $matchHomeTeamData = json_decode($match->home_team, true);
            $matchAwayTeamData = json_decode($match->away_team, true);
            // Compare goals if are diferent send notification
            if (
                (int)$row['home_team']['goals'] !== (int)$matchHomeTeamData['goals'] ||
                (int)$row['away_team']['goals'] !== (int)$matchAwayTeamData['goals']
            ) {
                $message = "{$row['time']} | {$row['home_team']['country']} {$row['home_team']['goals']} - {$row['away_team']['goals']} {$row['away_team']['country']}";
                $this->postToSlack($message);
                $this->info("notification sent - {$message}");
            }
            // Update the match in db
            $updateData = $row;
            unset($updateData['home_team_events']);
            unset($updateData['away_team_events']);
            $updateData['home_team'] = json_encode($updateData['home_team']);
            $updateData['away_team'] = json_encode($updateData['away_team']);
            $updateData['updated_at'] = Carbon::now();
            DB::table('matches')->where('id', $match->id)->update($updateData);
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
        if (empty($webhook)) {
            return false;
        }
        // Prepare data
        $data = [
            'channel' => env('SLACK_CHANNEL'),
            'icon_emoji' => ':soccer:',
            'text' => $message
        ];
        // Send request
        $client->post($webhook, [
            'headers' => [
                'content-type' => 'application/json'
            ],
            'body' => json_encode($data)
        ]);

        return true;
    }
}
