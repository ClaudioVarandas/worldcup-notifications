<?php

namespace App\Commands;

use Carbon\Carbon;
use GuzzleHttp\Client;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Facades\DB;

class FetchMatches extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'fetch:matches';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Fetch all matches';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $client = new Client(['base_uri' => 'http://worldcup.sfg.io']);

        $uri = 'matches';
        $res = $client->request('GET', $uri);

        $data = json_decode($res->getBody(), true);

        foreach ($data as $row) {
            unset($row['home_team_events']);
            unset($row['away_team_events']);
            $row['home_team'] = json_encode($row['home_team']);
            $row['away_team'] = json_encode($row['away_team']);
            $row['created_at'] = Carbon::now();
            $row['updated_at'] = Carbon::now();
            $match = DB::table('matches')->where('fifa_id', $row['fifa_id'])->first();
            if (is_null($match)) {
                DB::table('matches')->insert($row);
                $datetime = Carbon::parse($row['datetime'])->toDateTimeString();
                $this->info("Created : {$datetime} | {$row['home_team']['country']} - {$row['away_team']['goals']}");
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
        $schedule->command(static::class)->dailyAt('23:30:00');
    }
}
