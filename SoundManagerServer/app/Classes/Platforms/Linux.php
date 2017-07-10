<?php

namespace App\Classes\Platforms;

class Linux implements iPlatform
{
    const
        VOLUME_MAX = 65536,
        VOLUME_MIN = 0,
        VOLUME_SINGLE_STEP = 655.36,
        VOLUME_STEP = 5, //%
        BALANCE_MAX = 50, // right
        BALANCE_MIN = -50, // left
        BALANCE_DEF = 0;

    private
        $volume,
        $isMuted,
        $balance,
        $platformInfo;

    public function __construct()
    {
        $this->InitParameters();
    }

    /**
     * Method for increasing volume by calling native program amixer.
     *
     */
    public function VolumeUp()
    {
        $this->CalculateNewVolume(+self::VOLUME_STEP);

        shell_exec("amixer -D pulse sset Master {$this->volume['left']}%,{$this->volume['right']}%");
    }

    /**
     * Method for decreasing volume by calling native program amixer.
     *
     */
    public function VolumeDown()
    {
        $this->CalculateNewVolume(-self::VOLUME_STEP);

        shell_exec("amixer -D pulse sset Master {$this->volume['left']}%,{$this->volume['right']}%");
    }

    /**
     * Method for muting volume by calling native program amixer.
     *
     */
    public function VolumeMute()
    {
        shell_exec("amixer -D pulse sset Master mute");
    }

    /**
     * Method for un muting volume by calling native program amixer.
     *
     */
    public function VolumeUnmute()
    {
        shell_exec("amixer -D pulse sset Master unmute");
    }

    /**
     * Set volume by passing new value.
     *
     * @param $volume
     */
    public function SetVolume($volume)
    {
        shell_exec("amixer -D pulse sset Master " . ($volume * self::VOLUME_SINGLE_STEP));
    }

    /**
     * Method which will return current volume.
     *
     * @return mixed
     */
    public function GetVolume()
    {
        return $this->volume;
    }

    /**
     * Method which returns muted status.
     *
     * @return mixed
     */
    public function GetIsMuted()
    {
        return $this->isMuted;
    }

    public function SetBalance($newBalance)
    {
        $this->balance = $newBalance;

        $this->CalculateNewVolume(0);

        shell_exec("amixer -D pulse sset Master {$this->volume['left']}%,{$this->volume['right']}%");
    }

    /**
     * Method which returns current balance.
     *
     * @return mixed
     */
    public function GetBalance()
    {
        return $this->balance;
    }

    /**
     * Method for increasing/decreasing current volume. Volume will be changed according to
     * balance and param $newValue (can be positive or negative).
     *
     * @param $newVolume
     */
    private function CalculateNewVolume($newVolume)
    {

        $currMaxVol = max($this->volume['right'], $this->volume['left']) + $newVolume;

        $currMaxVol = (($currMaxVol > 100) ? 100 : ($currMaxVol < 0 ? 0 : $currMaxVol)); // volume must be in range 0-100

        $balanceCoef = (100 * abs($this->balance)) / self::BALANCE_MAX;

        $volumeCoef = ($balanceCoef * $currMaxVol / 100);

        // we make them equal in case balance is 0, and only one will be decreased
        $this->volume['left'] = $this->volume['right'] = $currMaxVol;

        if ($this->balance == 0) {
        } elseif ($this->balance > 0) {
            // decrease left volume
            $this->volume['left'] -= $volumeCoef;

            if ($this->volume['left'] > 100)
                $this->volume['left'] = 100;

            if ($this->volume['left'] < 0)
                $this->volume['left'] = 0;
        } else {
            // decrease right volume
            $this->volume['right'] -= $volumeCoef;

            if ($this->volume['right'] > 100)
                $this->volume['right'] = 100;

            if ($this->volume['right'] < 0)
                $this->volume['right'] = 0;
        }
    }

    /**
     * Method that will be called inside class constructor and will initiate class properties.
     *
     */
    private function InitParameters()
    {
        $outputWithNewLines = nl2br(shell_exec("amixer -D pulse sget Master"));
        $arrayOutput = explode(PHP_EOL, $outputWithNewLines);

        $volumeLeft = $arrayOutput[5];
        $volumeRight = $arrayOutput[6];

        // isMuted
        $posIsMutedLeft = strripos($volumeLeft, "[");
        $posIsMutedRight = strripos($volumeRight, "[");

        $isMutedLeft = substr($volumeLeft, $posIsMutedLeft + 1, 3);
        $isMutedRight = substr($volumeRight, $posIsMutedRight + 1, 3);

        $this->isMuted = stripos($isMutedLeft, "off") !== false;

        // set volume

        $posLeftStart = strpos($volumeLeft, "[");
        $posRightStart = strpos($volumeRight, "[");

        $volumeLeftWithPercent = substr($volumeLeft, $posLeftStart + 1, 3);
        $volumeRightWithPercent = substr($volumeRight, $posRightStart + 1, 3);

        $this->volume = ["left" => (int)strtr($volumeLeftWithPercent, array("]" => "", "%" => "")),
            "right" => (int)strtr($volumeRightWithPercent, array("]" => "", "%" => ""))];

        // balance
        if ($this->volume['left'] == $this->volume['right']) {
            $this->balance = 0;
        } else {
            $max = $min = 0;
            $sign = null;
            if ($this->volume['left'] > $this->volume['right']) {
                $max = $this->volume['left'];
                $min = $this->volume['right'];
                $sign = -1;
            } else {
                $min = $this->volume['left'];
                $max = $this->volume['right'];
                $sign = +1;
            }

            $diff = $max - $min;
            $diffInPercent = (self::BALANCE_MAX * $diff) / $max;

            $this->balance = $diffInPercent * $sign;
        }

        // system
        $this->platformInfo = [
            "os" => php_uname('s'),
            "hostname" => php_uname('n'),
            "machinetype" => php_uname('m')
        ];
    }

    /**
     *
     *
     * @return array
     */
    public function GetStatus()
    {
        $volume = $this->volume;
        $balance = $this->balance;
        $isMuted = $this->isMuted;
        $platformInfo = $this->platformInfo;

        return compact("platformInfo", "volume", "balance", "isMuted");
    }

    // system controlls (for linux, is needed root credentials for now ill be on hold).
    public function ShutDownServer()
    {
        #shell_exec("shutdown -h now");
    }

    public function SleepServer()
    {
        #shell_exec("pmi action suspend");
    }

    public function ResetServer()
    {
        #shell_exec("shutdown -r now");
    }

    public function LockServer()
    {
        #shell_exec("gnome-screensaver-command -l");
    }

    public function LogOffCurrentUser()
    {
        #shell_exec("gnome-session-save --force-logout");
    }
}