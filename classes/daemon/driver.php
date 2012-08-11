<?php
/**
 * Daemon_Driver
 *
 * @package	fuel-daemon
 * @version	0.1
 * @author	chalharu
 * @license	MIT License
 * @copyright	Copyright 2012, chalharu
 * @link	http://chrysolite.hatenablog.com/
 */

namespace Daemon;

/**
 * Daemon_Driver class
 */
class Daemon_Driver
{
    protected $callback = NULL;
    protected static $shm_id = NULL;
    protected $child_pid = NULL;

    public function setCallback($callback){
        if(!is_callable($callback)){
            throw new \FuelException();
		}
        $this->callback = $callback;
        return $this;
    }

    public function run()
    {
        if($this->callback == NULL)
            throw new \FuelException();

        $this->daemonize();
       
       	declare(ticks = 1){
            while(self::getLiving()){
                $signo = self::sig_handler();

                switch ($signo){
                case SIGTERM:   // 終了シグナル
                    static::setLiving(FALSE); // 終了
                    posix_kill($this->child_pid,SIGINT); //SIGINT は Ctrl+Cと同じ
                    break;
                case SIGHUP:	// HUPシグナル
                    // デーモン再起動処理
                    break;
                case SIGCHLD:   // 子プロセスが終了した場合
                    static::setLiving(FALSE);
                    // 子プロセスのリソースの回収
                    pcntl_waitpid(-1, $status, WNOHANG);
                    break;
                case NULL:	  // シグナルがない場合
                    sleep(1);   // 適当な処理
                    break;
                default:
                }
            }
        }
    }

    const MAX_SIG_QUEUE_LEN = 1024;
    const SHM_LIVING = 0x1;
    protected static $s_signo = array();

    static function sig_handler($signo = NULL)
    {
        if (is_null($signo))
            return array_shift(static::$s_signo);
        
        static::$s_signo[] = $signo;
        if ( count(static::$s_signo) >= static::MAX_SIG_QUEUE_LEN )
            array_shift(static::$s_signo);
    }

    protected function child_process()
    {
        call_user_func($this->callback);
    }

    static function getLiving()
    {
        return shm_get_var(static::$shm_id, static::SHM_LIVING);
    }

    static function setLiving($living)
    {
        return shm_put_var(static::$shm_id, static::SHM_LIVING,(boolean)$living);
    }

    protected function daemonize()
    {
        $shm_key = ftok(__FILE__, 't');
        static::$shm_id = shm_attach($shm_key);
        static::setLiving(TRUE);
        
        if ( pcntl_fork() != 0 )
            exit;
        posix_setsid();
        if ( pcntl_fork() != 0 )
            exit;
        $pid = pcntl_fork();
        if( $pid == -1 ){
            exit;
        } elseif ( $pid == 0 ) {
            chdir("/");
            umask(0);
            pcntl_signal(SIGTERM, SIG_IGN);
            pcntl_signal(SIGHUP, SIG_IGN);
            pcntl_signal(SIGCHLD, SIG_IGN);
            $this->child_process();
        }
        
        $this->child_pid = $pid;
 
        chdir("/");
        umask(0);
        pcntl_signal(SIGTERM, "\\Daemon\\Daemon_Driver::sig_handler");
        pcntl_signal(SIGHUP, "\\Daemon\\Daemon_Driver::sig_handler");
        pcntl_signal(SIGCHLD, "\\Daemon\\Daemon_Driver::sig_handler");
    }
}