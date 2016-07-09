<?php

namespace App\Listeners;

use App\Events\NewSSH;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

use App\Models\DeviceIPs;

use App\Models\SSHCredentials;

use App\Models\Devices;

use phpseclib\Net\SSH2;

use Log;

class DiscoverDevice
{
    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  NewSSH  $event
     * @return void
     */
    public function handle(NewSSH $event)
    {
        $SSHcredentials = $event->address;
        
        foreach($SSHcredentials as $SSHcredential){
        
        $ssh = new SSH2($SSHcredential->IP->address);
            if (!$ssh->login($SSHcredential['username'],$SSHcredential['password'] )) {
                
                Log::error('SSH Credentials Rejected ID:'.$SSHcredential['id']);

                exit;
            }
         
         $data = $ssh->exec(" /sbin/ifconfig");
         
         if(preg_match('/((?:[a-zA-Z0-9]{2}[:-]){5}[a-zA-Z0-9]{2})/', $data,$macmatch)){
             
            $mac = $macmatch[0];
 
            $url = "http://api.macvendors.com/" . urlencode($mac);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            $vendor = curl_exec($ch); 
            
         }else{
             // MAC not Found
              Log::error('Failed to Find MAC'.$SSHcredentials['id']);
             exit;
         }
				
         if($vendor == 'UBIQUITI NETWORKS INC.'){
             
             $data = $ssh->exec("iwconfig");
             
             if(str_contains($data,'ath0')){
                // Device is a Radio
                $data = $ssh->exec("vi /etc/board.inc");
                
                if(preg_match('/\$board_name="(.{0,30})\";/', $data,$model)){
                    $model = $model[1];
                    $type = NULL;
                    $sn = NULL;
                }else{
                    // Model not found
                     Log::error('Failed to Find Radio Model ID:'.$SSHcredential['id']);
                    exit;
                }
             }else{
                // Device is not a Radio therefore is EdgeOS
                $data = $ssh->exec("/opt/vyatta/bin/vyatta-op-cmd-wrapper show version");
                
                if(preg_match('/\HW model:\s{5}(.{1,35})\n\HW S\/N:/', $data,$model)){
                    $model = $model[1];
                    
                    if(str_contains($model,'EdgeSwitch')){
                        $type = 'Switch';
                    }else{
                        $type = 'Router';   
                    }    
                }else{
                    // Model not found
                     Log::error('Failed to Find EdgeOS Model ID:'.$SSHcredential['id']);
                    exit;
                }
                
                if(preg_match('/\HW S\/N:\s{7}(.{1,35})\n/', $data,$sn)){
                    $sn = $sn[1];
                    
                }else{
                    // SN not found
                     Log::error('Failed to Find EdgeOS Serial Number ID:'.$SSHcredential['id']);
                    exit;
                }
             }
             
         }
         
         Devices::create([
            'name' => NULL,
            'type' => $type,
            'model' => $model,
            'manufacturer' => $vendor,
            'mac' => $mac,
            'serial_number' => $sn
        ]);
         
        }
    }
}