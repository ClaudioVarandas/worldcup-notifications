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

        $uri = $type != 'all' ? "matches/{$type}" : 'matches';

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
            unset($row['home_team_events']);
            unset($row['away_team_events']);
            $datetime = Carbon::parse($row['datetime'])->toDateTimeString();
            $message = "{$datetime} | {$row['home_team']['country']} - {$row['away_team']['country']}";
            $row['home_team'] = json_encode($row['home_team']);
            $row['away_team'] = json_encode($row['away_team']);
            $row['created_at'] = Carbon::now();
            $row['updated_at'] = Carbon::now();
            $match = DB::table('matches')->where('fifa_id', $row['fifa_id'])->first();
            if (is_null($match)) {
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
        $webhook = env('SLACK_WEBOOK_URL');
        if (empty($webhook)) {
            return false;
        }

        $pretextMessage = $type == 'today' ? "Today matches" : "Tomorrow matches";

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
            if ($row['status'] == 'completed' || $row['status'] == 'in progress') {
                $attachments[$key]['fields'][] = [
                    "title" => 'Score',
                    "value" => "{$row['home_team']['code']} {$row['home_team']['goals']} - {$row['away_team']['code']} {$row['away_team']['goals']}",
                    "short" => true
                ];
            }
        }
        // Prepare data
        $postData = [
            'channel' => env('SLACK_CHANNEL'),
            'icon_emoji' => ':soccer:',
        ];
        // Send requests
        $postData['text'] = $pretextMessage;
        $client->post($webhook, [
            'headers' => [
                'content-type' => 'application/json'
            ],
            'body' => json_encode($postData)
        ]);
        unset($postData['text']);
        $postData['attachments'] = $attachments;
        $client->post($webhook, [
            'headers' => [
                'content-type' => 'application/json'
            ],
            'body' => json_encode($postData)
        ]);

        return true;
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     */
    public function schedule(Schedule $schedule): void
    {
        $schedule->command(static::class, ['today'])->hourly()->between('8:00', '23:30');
    }
}
