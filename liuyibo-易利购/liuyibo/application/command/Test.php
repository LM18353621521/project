<?php
namespace app\command;


use think\Config;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class Test extends Command
{
    protected function configure(){
        $this->setName('Test')->setDescription("计划任务 Test");
    }

    protected function execute(Input $input, Output $output){
        $output->writeln('Date Crontab job start...');
        /*** 这里写计划任务列表集 START ***/


        $this->doCron();

        /*** 这里写计划任务列表集 END ***/
        $output->writeln('Date Crontab job end...');
    }

    private function test(){
        echo "test\r\n";
    }
    public function doCron()
    {
        // 记录开始运行的时间
        $GLOBALS['_beginTime'] = microtime(TRUE);

        /* 永不超时 */
        ini_set('max_execution_time', 0);
        $time   = time();
        $exe_method = [];
        $crond_list = Config::get('crond');   //获取第四步的文件配置，根据自己版本调整一下
        $sys_crond_timer = Config::get('sys_crond_timer');
        foreach ( $sys_crond_timer as $format )
        {
            $key = date($format, ceil($time));

            if ( is_array(@$crond_list[$key]) )
            {
                $exe_method = array_merge($exe_method, $crond_list[$key]);
            }
        }


        if (!empty($exe_method))
        {
            foreach ($exe_method as $method)
            {
                if(!is_callable($method))
                {
                    //方法不存在的话就跳过不执行
                    continue;
                }

                echo "执行crond --- {$method}()\n";
                $runtime_start = microtime(true);

                call_user_func($method);

                $runtime = microtime(true) - $runtime_start;

                echo "{$method}(), 执行时间: {$runtime}\n\n";
            }

            $time_total = microtime(true) - $GLOBALS['_beginTime'];
            echo "total:{$time_total}\n";
        }
    }
    public function firstTest(){
        $filename = 'crontab.txt';
        $word = date('Y-m-d H:i:s',time() . '\r\n');
        $fh = fopen($filename, "a");
        fwrite($fh,$word);
        fclose($fh);

    }

}