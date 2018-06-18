<?php

namespace App\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class TestWebhooks extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'test:webhook {team} {channel?}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Test webhook';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $client = new Client();
        $webhooks = config('slack.teams');
        $team = $this->argument('team');
        $channel = $this->argument('channel');

        if(is_null($webhooks[$team])){
            return;
        }
        // Prepare data
        $data = [
            'icon_emoji' => ':soccer:',
            'text' => 'This is a test message.'
        ];

        if(!is_null($channel)){
            $data['channel'] = $channel;
        }else{
            if(!is_null($webhooks[$team]['channel'])){
                $data['channel'] = $webhooks[$team]['channel'];
            }
        }
        // Send request
        $client->post($webhooks[$team]['url'], [
            'headers' => [
                'content-type' => 'application/json'
            ],
            'body' => json_encode($data)
        ]);
    }

    /**
     * Define the command's schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule $schedule
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
