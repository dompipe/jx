<?php declare(strict_types=1);

namespace {
    require_once dirname(__DIR__) . '/Plugin.php';
}

namespace jx\plugins {

use JsonSerializable;
use jx\Boundary;
use jx\JxException;
use jx\JxPlugin;
use jx\Plugins;

/** Host-neutral audio signal binding for waveform, level, pitch, beat, tempo and channels. */
final class AudioSignalBinding implements JsonSerializable
{
    public const MODES = ['waveform','level','pitch','beat','tempo','channels'];
    private string $id;
    private array $with;

    public function __construct(
        private string $mode,
        private string $media,
        private string $bag,
        private string $at='_default',
        array $with=[],
    ) {
        $this->mode=strtolower(trim($this->mode));
        if(!in_array($this->mode,self::MODES,true)) throw new JxException('Unsupported audio signal mode','plugin.audio-signals',true,['mode'=>$this->mode]);
        $this->media=self::name($this->media,'media');
        $this->bag=self::name($this->bag,'Bag');
        $this->at=self::node($this->at);
        $this->with=self::options($this->mode,$with);
        $this->id=substr(hash('sha256',"audio-signal-v1\0".serialize([$this->mode,$this->media,$this->bag,$this->at,$this->with])),0,24);
    }

    public function id():string{return $this->id;}
    public function mode():string{return $this->mode;}
    public function options():array{return $this->with;}

    public function jsonSerialize():array
    {
        return [
            'kind'=>'binding','binding'=>'media.audio.'.$this->mode,'plugin'=>'audio-signals','version'=>AudioSignalsPlugin::VERSION,
            'id'=>$this->id,'source'=>['media'=>$this->media],'target'=>['bag'=>$this->bag,'at'=>$this->at],
            'reactive'=>true,'with'=>$this->with,'rows'=>self::rows($this->mode),
        ];
    }

    private static function rows(string $mode):array
    {
        return match($mode){
            'waveform'=>['sample'=>'sample','time'=>'time','value'=>'value','channel'=>'channel'],
            'level'=>['time'=>'time','peak'=>'peak','rms'=>'rms','db'=>'db'],
            'pitch'=>['time'=>'time','hz'=>'hz','confidence'=>'confidence','note'=>'note'],
            'beat'=>['time'=>'time','hit'=>'hit','strength'=>'strength','interval'=>'interval'],
            'tempo'=>['time'=>'time','bpm'=>'bpm','confidence'=>'confidence'],
            'channels'=>['time'=>'time','left'=>'left','right'=>'right','balance'=>'balance','correlation'=>'correlation'],
        };
    }

    private static function options(string $mode,array $with):array
    {
        $with=Boundary::import($with);
        foreach($with as $key=>$value) if(preg_match('/secret|password|token/i',(string)$key)) throw new JxException('Secrets cannot be stored in audio signal options','plugin.audio-signals',true,['key'=>$key]);
        $every=(float)($with['every_ms']??($mode==='waveform'?33.0:50.0));
        if(!is_finite($every)||$every<8.0||$every>60000.0) throw new JxException('Audio signal every_ms must be 8..60000','plugin.audio-signals',true);
        $history=(int)($with['history']??($mode==='waveform'?2048:256));
        if($history<1||$history>200000) throw new JxException('Audio signal history must be 1..200000','plugin.audio-signals',true);
        $size=(int)($with['samples']??1024);
        if($size<32||$size>32768) throw new JxException('Audio signal samples must be 32..32768','plugin.audio-signals',true);
        $threshold=(float)($with['threshold']??0.16);
        if(!is_finite($threshold)||$threshold<0.0||$threshold>1.0) throw new JxException('Audio signal threshold must be between 0 and 1','plugin.audio-signals',true);
        return ['every_ms'=>$every,'history'=>$history,'samples'=>$size,'threshold'=>$threshold];
    }

    private static function name(string $value,string $what):string
    {
        $value=trim($value);
        if($value===''||strlen($value)>128||preg_match('/[^a-z0-9._-]/i',$value)) throw new JxException("Invalid {$what} name",'plugin.audio-signals',true,[$what=>$value]);
        return $value;
    }
    private static function node(string $value):string
    {
        $value=trim($value);
        if($value===''||strlen($value)>256||str_contains($value,"\0")) throw new JxException('Invalid audio signal Bag node','plugin.audio-signals',true);
        return $value;
    }
}

final class AudioSignalsPlugin implements JxPlugin
{
    public const VERSION='jx.audio-signals/1';
    public function id():string{return 'audio-signals';}
    public function version():string{return self::VERSION;}
    public function capabilities():array{return ['audio.waveform','audio.level','audio.pitch','audio.beat','audio.tempo','audio.channels'];}
    private static function make(string $mode,string $fromMedia,string $toBag,string $at,array $with):AudioSignalBinding{return new AudioSignalBinding($mode,$fromMedia,$toBag,$at,$with);}
    public static function waveform(string $fromMedia,string $toBag,string $at='_default',array $with=[]):AudioSignalBinding{return self::make('waveform',$fromMedia,$toBag,$at,$with);}
    public static function level(string $fromMedia,string $toBag,string $at='_default',array $with=[]):AudioSignalBinding{return self::make('level',$fromMedia,$toBag,$at,$with);}
    public static function pitch(string $fromMedia,string $toBag,string $at='_default',array $with=[]):AudioSignalBinding{return self::make('pitch',$fromMedia,$toBag,$at,$with);}
    public static function beat(string $fromMedia,string $toBag,string $at='_default',array $with=[]):AudioSignalBinding{return self::make('beat',$fromMedia,$toBag,$at,$with);}
    public static function tempo(string $fromMedia,string $toBag,string $at='_default',array $with=[]):AudioSignalBinding{return self::make('tempo',$fromMedia,$toBag,$at,$with);}
    public static function channels(string $fromMedia,string $toBag,string $at='_default',array $with=[]):AudioSignalBinding{return self::make('channels',$fromMedia,$toBag,$at,$with);}
}

Plugins::register(new AudioSignalsPlugin());

}
