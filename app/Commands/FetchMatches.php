<?php

namespace App\Commands;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\DB;

class FetchMatches extends Command
{
    const ALLOWED_TYPES = [
        'all',
        'today',
        'tomorrow'
    ];

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'fetch:matches {type=all : List type all|today|tomorrow}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Fetch matches';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $type = $this->argument('type');

        if (!in_array($type, self::ALLOWED_TYPES)) {
            return;
        }

        $uri = $type != 'all' ? "matches/{$type}" : 'matches?details=true';

        $client = new Client(['base_uri' => env('WORLDCUP_API')]);
        $res = $client->request('GET', $uri);
        $data = json_decode($res->getBody(), true);

        if ($type == 'all') {
            $this->processMatches($data);
        } else {
            $this->postToSlack($data, $type);
        }

    }

    public function processMatches(array $data)
    {
        foreach ($data as $row) {
            $datetime = Carbon::parse($row['datetime'])->toDateTimeString();
            $message = "{$datetime} | {$row['home_team']['country']} - {$row['away_team']['country']}";
            $row['home_team'] = json_encode($row['home_team']);
            $row['away_team'] = json_encode($row['away_team']);
            $row['home_team_events'] = isset($row['home_team_events']) ? json_encode($row['home_team_events']) : null;
            $row['away_team_events'] = isset($row['away_team_events']) ? json_encode($row['away_team_events']) : null;
            $row['home_team_statistics'] = isset($row['home_team_statistics']) ? json_encode($row['home_team_statistics']) : null;
            $row['away_team_statistics'] = isset($row['away_team_statistics']) ? json_encode($row['away_team_statistics']) : null;
            $row['updated_at'] = Carbon::now();
            $match = DB::table('matches')->where('fifa_id', $row['fifa_id'])->first();
            if (is_null($match)) {
                $row['created_at'] = Carbon::now();
                DB::table('matches')->insert($row);
                $this->info("Created : " . $message);
            } else {
                DB::table('matches')->where('id', $match->id)->update($row);
                $this->info("Updated : " . $message);
            }
        }
    }

    protected function postToSlack(array $data, string $type)
    {
        $client = new Client();
        $pretextMessage = $type == 'today' ? "Today matches status" : "Tomorrow matches";

        $attachments = [];
        foreach ($data as $key => $row) {
            $datetime = Carbon::parse($row['datetime'])->timezone('Europe/Lisbon');
            $attachments[$key]['fallback'] = "{$datetime->toDateTimeString()} | {$row['home_team']['country']} - {$row['away_team']['country']}";

            switch ($row['status']) {
                case 'completed':
                    $attachments[$key]['color'] = "#ff0000";
                    break;
                case 'in progress':
                    $attachments[$key]['color'] = "#fcff66";
                    break;
                default:
                    $attachments[$key]['color'] = "#36a64f";
                    break;
            }

            $homeTeamStatistics = $row['home_team_statistics'];
            $awayTeamStatistics = $row['away_team_statistics'];
            $attachments[$key]['title'] = "{$row['home_team']['country']} - {$row['away_team']['country']}";
            $attachments[$key]['fields'][] = [
                "title" => 'Venue',
                "value" => $row['venue'],
                "short" => true
            ];
            $attachments[$key]['fields'][] = [
                "title" => 'Location',
                "value" => $row['location'],
                "short" => true
            ];
            $attachments[$key]['fields'][] = [
                "title" => 'Datetime',
                "value" => $datetime->toDateTimeString(),
                "short" => true
            ];
            $attachments[$key]['fields'][] = [
                "title" => 'Status',
                "value" => $row['status'],
                "short" => true
            ];
            if(is_array($homeTeamStatistics)){
                $attachments[$key]['fields'][] = [
                    "title" => 'Home Team Stats',
                    "value" => $this->processTeamStats($homeTeamStatistics),
                    "short" => false
                ];
            }
            if(is_array($awayTeamStatistics)){
                $attachments[$key]['fields'][] = [
                    "title" => 'Away Team Stats',
                    "value" => $this->processTeamStats($awayTeamStatistics),
                    "short" => false
                ];
            }
            if ($row['status'] == 'completed' || $row['status'] == 'in progress') {
                $attachments[$key]['fields'][] = [
                    "title" => 'Score',
                    "value" => "{$row['home_team']['code']} {$row['home_team']['goals']} - {$row['away_team']['code']} {$row['away_team']['goals']}",
                    "short" => true
                ];
            }
        }


        $webhooks = config('slack.teams');
        foreach ($webhooks as $team => $webhook) {
            if (empty($webhook['url'])) {
                continue;
            }
            // Prepare data
            $postData = [
                'icon_emoji' => ':soccer:',
            ];
            if (!is_null($webhook['channel'])) {
                $postData['channel'] = $webhook['channel'];
            }
            // Send requests
            $postData['text'] = $pretextMessage;
            $client->post($webhook['url'], [
                'headers' => [
                    'content-type' => 'application/json'
                ],
                'body' => json_encode($postData)
            ]);
            unset($postData['text']);
            $postData['attachments'] = $attachments;
            $client->post($webhook['url'], [
                'headers' => [
                    'content-type' => 'application/json'
                ],
                'body' => json_encode($postData)
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
        $schedule->command(static::class, ['today'])->dailyAt('08:30')->timezone(config('app.timezone'));
        $schedule->command(static::class, ['today'])->dailyAt('15:00')->timezone(config('app.timezone'));
        $schedule->command(static::class, ['today'])->dailyAt('18:30')->timezone(config('app.timezone'));
        $schedule->command(static::class, ['today'])->dailyAt('21:30')->timezone(config('app.timezone'));
    }

    private function processTeamStats(array $stats): string
    {
        $teamStats = "Attempts On Goal: " . $stats['attempts_on_goal'] ."\n";
        $teamStats .= "On Target: " . $stats['on_target'] ."\n";
        $teamStats .= "Off Target: " . $stats['off_target'] ."\n";
        $teamStats .= "Blocked: " . $stats['blocked'] ."\n";
        $teamStats .= "Woodwork: " . $stats['woodwork'] ."\n";
        $teamStats .= "Corners: " . $stats['corners'] ."\n";
        $teamStats .= "Offsides: " . $stats['offsides'] ."\n";
        $teamStats .= "Ball possession: " . $stats['ball_possession'] ."%\n";
        $teamStats .= "Pass Accuracy: " . $stats['pass_accuracy'] ."%\n";
        $teamStats .= "Number of passes: " . $stats['num_passes'] ."\n";
        $teamStats .= "Passes completed: " . $stats['passes_completed'] ."\n";
        $teamStats .= "Distance covered: " . $stats['distance_covered'] ."km\n";
        $teamStats .= "Tackles: " . $stats['tackles'] ."\n";
        $teamStats .= "Clearances: " . $stats['clearances'] ."\n";
        $teamStats .= "Yellow Cards: " . $stats['yellow_cards'] ."\n";
        $teamStats .= "Red Cards: " . $stats['red_cards'] ."\n";
        $teamStats .= "Fouls committed: " . $stats['fouls_committed'] ."\n";

        return $teamStats;
    }
}
