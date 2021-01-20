<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;

use App\User;

class DecryptString extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'utils:decrypt {str}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Decrypts encrypted string';

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
     * @return int
     */
    public function handle(): Int
    {
        $uID = Crypt::decryptString($this->argument("str"));
        $this->info($uID);

        $uData = User::find($uID);
        if(!$uData) {
            $this->warn("Could not find user in database. Are you running on production?");
            return 1;
        }

        $this->info(
            "username: " . $uData->name . "\n" . "email: " . $uData->email . "\n" . "invited_code: " . $uData->invited_code
        );

        return 0;
    }
}
