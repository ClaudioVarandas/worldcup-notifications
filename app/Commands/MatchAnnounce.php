<?php

namespace App\Commands;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\DB;

class MatchAnnounce extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'match:announce';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Match announcements - Score,Start,End';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $client = new Client(['base_uri' => env('WORLDCUP_API')]);
        $res = $client->request('GET', 'matches');
        $data = json_decode($res->getBody(), true);

        foreach ($data as $key => $row) {
            $matchData = DB::table('matches')->where('fifa_id', $row['fifa_id'])->first();
            // Notify start and end
            if ($matchData->status == 'future' && $row['status'] == 'in progress') {
                $message = "MATCH STARTED | {$row['home_team']['country']} - {$row['away_team']['country']}";
                $this->postToSlack($message);
                $this->updateMatch($matchData->id, $row);
            } elseif ($matchData->status == 'in progress' && $row['status'] == 'completed') {
                $message = "MATCH ENDED | {$row['home_team']['country']} {$row['home_team']['goals']} - {$row['away_team']['goals']} {$row['away_team']['country']}";
                $this->postToSlack($message);
                $this->updateMatch($matchData->id, $row);
            }
            // If the match not live continue
            if ($row['status'] != 'in progress') {
                continue;
            }
            $matchData = DB::table('matches')->where('fifa_id', $row['fifa_id'])->first();
            $matchHomeTeamData = json_decode($matchData->home_team, true);
            $matchAwayTeamData = json_decode($matchData->away_team, true);
            $matchHomeTeamEventsData = json_decode($matchData->home_team_events, true);
            $matchAwayTeamEventsData = json_decode($matchData->away_team_events, true);
            // Compare goals if are diferent send notification
            if (
                (int)$row['home_team']['goals'] !== (int)$matchHomeTeamData['goals'] ||
                (int)$row['away_team']['goals'] !== (int)$matchAwayTeamData['goals']
            ) {
                $message = "{$row['time']} | {$row['home_team']['country']} {$row['home_team']['goals']} - {$row['away_team']['goals']} {$row['away_team']['country']}";
                $this->postToSlack($message);
                $this->info("notification sent - {$message}");
            }

            $homeTeamLiveEventsData = $row['home_team_events'];
            $awayTeamLiveEventsData = $row['away_team_events'];
            if (count($homeTeamLiveEventsData) > count($matchHomeTeamEventsData)) {
                $event = end($homeTeamLiveEventsData);
                $message = "{$row['home_team']['country']} | {$event['time']} | {$event['type_of_event']} - {$event['player']}";
                $this->postToSlack($message);
            }
            if (count($awayTeamLiveEventsData) > count($matchAwayTeamEventsData)) {
                $event = end($awayTeamLiveEventsData);
                $message = "{$row['home_team']['country']} | {$event['time']} | {$event['type_of_event']} - {$event['player']}";
                $this->postToSlack($message);
            }

            $this->updateMatch($matchData->id, $row);
        }
    }

    protected function updateMatch($matchId, array $data)
    {
        // Update the match in db
        $updateData = $data;
        $updateData['home_team_events'] = json_encode($data['home_team_events']);
        $updateData['away_team_events'] = json_encode($data['away_team_events']);
        $updateData['home_team'] = json_encode($data['home_team']);
        $updateData['away_team'] = json_encode($data['away_team']);
        $updateData['updated_at'] = Carbon::now();
        $result = DB::table('matches')->where('id', $matchId)->update($updateData);
        return $result;
    }

    protected function postToSlack($message)
    {
        $client = new Client();
        $webhooks = config('slack.webhooks');
        foreach ($webhooks as $webhook => $channel) {
            if (empty($webhook)) {
                continue;
            }
            // Prepare data
            $data = [
                'icon_emoji' => ':soccer:',
                'text' => $message
            ];
            if(!is_null($channel)){
                $data['channel'] = $channel;
            }
            // Send request
            $client->post($webhook, [
                'headers' => [
                    'content-type' => 'application/json'
                ],
                'body' => json_encode($data)
            ]);
        }
        return true;
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     */
    public function schedule(Schedule $schedule): void
    {
        $schedule->command(static::class)->everyMinute()->withoutOverlapping();
    }
}
