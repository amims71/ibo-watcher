<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class IboWatch extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'watch:video {token} {vid}';
    protected $token;
    protected $start='https://www.ibotuber.com/api/user/watchstart';
    protected $addView='https://www.ibotuber.com/api/public/addViews';
    protected $addHistory='https://www.ibotuber.com/api/user/addhistory';
    protected $watchCount='https://www.ibotuber.com/api/user/watchcount';
    protected $end='https://www.ibotuber.com/api/user/claimearning';
    protected $homepage='https://www.ibotuber.com/api/public/homepage?vId=';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

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
     * @return void
     */
    public function handle()
    {
        $vid=$this->argument('vid')==='a'?'':$this->argument('vid');
        $this->token=$this->argument('token');
        $this->crawler($vid);
    }


    private function crawler($vid){
        $this->alert('Getting More Videos with '.$vid);
        $response=Http::get($this->homepage,[
                'vId'=>$vid
            ]);
        $videos=json_decode($response->body())->details->videos;
        $totalVideos=count($videos);
        foreach ($videos as $key=>$video){
            $this->earnFrom($video->_id);
            if (++$key==$totalVideos) $this->crawler($video->_id);
        }

    }

    private function earnFrom($vid){
        $this->warn('Working on video '.$vid);
        $response = $this->responseFrom(0,$this->start,$vid);
        if ($response->json()['statusCode']==200) {
            $this->info('Start ' . $response->body());
            $this->watchVideo($vid);
            $this->responseFrom('End',$this->end,$vid);
        }
    }

    private function watchVideo($vid){
        for ($i=1;$i>0;$i++){
            $this->responseFrom('Add View',$this->addView,$vid);
            $this->responseFrom('Add History',$this->addHistory,$vid);
            $response = $this->responseFrom('Add Count number '.$i,$this->watchCount,$vid,$i);
            if ($response->json()['statusCode']==429) {
                $this->info('Waiting 5 seconds');
                sleep(5);
                $response = $this->responseFrom('Add Count number '.$i,$this->watchCount,$vid,$i);
            }
            if ($response->json()['statusCode']==403) break;
            $this->info('Waiting 40 seconds');
            sleep(40);
        }
    }

    private function responseFrom($info,$route,$vid,$timestamp=0){
        $requestBody=[
            'vId' => $vid
        ];
        if ($timestamp) $requestBody['timeStamp']=$timestamp;
        $response = Http::withHeaders([
            "Authorization" => "Bearer " . $this->token
        ])
            ->post($route, $requestBody);
        if ($info==="End") $this->question($info.' '.$response->body());
        elseif ($info) $this->info($info.' '.$response->body());
        return $response;
    }
}
