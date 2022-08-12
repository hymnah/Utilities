<?php

/**
 * @author Albert Igcasan <jaigcasan@gmail.com>
 * @usage
 *      $debugger = new Debugger()
 *      $debugger->start(<debug message>);
 *      $debugger->end(<debug message>);
 *      $debugger->summary();
 */

class Debugger
{
    private $logFile;
    private $debugFile;
    private $debugStarts = [];
    private $summary = [];

    const SEPARATOR_LEN = 7;
    const DEBUG_HEADER_SYMBOL = '*';
    const DEBUG_SUMMARY_HEADER = '      DEBUG INFO SUMMARY      ';
    const DEBUG_DEFAULT_PROC_NAME = 'Default process name';

    public function __construct($logFile = '/tmp/debug.log')
    {
        $this->logFile = $logFile;
    }

    public function start($msg = self::DEBUG_DEFAULT_PROC_NAME)
    {
        $backtrace = debug_backtrace();
        $startingline = $backtrace[0]['line'];
        $filename = $backtrace[0]['file'];
        $microtime = $this->getMicrotime();
        $data = 'START: ' . $msg;
        $data = $microtime->format("Y-m-d H:i:s.u") . ' | ' . $filename . ' | Line ' . $startingline . ' | ' . $data;
        $this->writeToLogs($data);

        $count = count($this->debugStarts);
        $this->debugStarts[$count] = [
            'microtime' => $microtime,
            'startingline' => $startingline,
            'debugMsg' => $msg
        ];
    }

    public function end($msg = self::DEBUG_DEFAULT_PROC_NAME)
    {
        $backtrace = debug_backtrace();
        $endingline = $backtrace[0]['line'];
        $filename = $backtrace[0]['file'];
        $count = count($this->debugStarts) - 1;
        $microtime = $this->getMicrotime();

        if ($msg === self::DEBUG_DEFAULT_PROC_NAME) {
            $msg = $this->debugStarts[$count]['debugMsg'];
        }

        $data = 'END: ' . $msg;
        $data = $microtime->format("Y-m-d H:i:s.u") . ' | ' . $filename . ' | Line ' . $endingline . ' | ' . $data;
        $diff = $this->microDiff($this->debugStarts[$count]['microtime'], $microtime);

        $performTime = '. Took ' . $diff . ' second(s)';
        $this->writeToLogs($data . $performTime);

        $this->summary[] = $filename . '|' . $msg  . '|Lines: ' . $this->debugStarts[$count]['startingline'] . '-' . $endingline . '|' . $diff . ' second(s)';
        unset($this->debugStarts[$count]);
    }

    public function summary()
    {
        $summary = $this->summary;

        $msglengths = array_map(array($this, 'debugMsgLengths'), $summary);
        $longestMsg = max($msglengths);
        $msgAllowance = $longestMsg + self::SEPARATOR_LEN;

        $datas = [];

        foreach ($summary as $rows) {
            $expRows = explode('|', $rows);
            $debugFile = $expRows[0];
            $debugMsg = $expRows[1];
            $debugFileDetails = $expRows[2];
            $debugTime = $expRows[3];

            $debugMsgLen = strlen($debugMsg);
            $debugMsgRepeat = $msgAllowance - $debugMsgLen;
            $debugMsgDashes = str_repeat('-', $debugMsgRepeat);

            $debugDetailsRepeat = self::SEPARATOR_LEN;
            $debugDetailsDashes = str_repeat('-', $debugDetailsRepeat);

            $datas[$debugFile][] = $debugMsg . $debugMsgDashes . $debugFileDetails . $debugDetailsDashes . $debugTime;
        }

        $longestDatas = [];

        foreach ($datas as $data) {
            $dataslengths = array_map('strlen', $data);
            $longestDatas[] = max($dataslengths);
        }

        $longestData = max($longestDatas);

        if ($longestData & 1) {
            $longestData -= 1;
        }

        $headerBorderChar = self::DEBUG_HEADER_SYMBOL;
        $headerBorder = str_repeat($headerBorderChar, $longestData);
        $sideLen = ($longestData - strlen(self::DEBUG_SUMMARY_HEADER)) / 2;
        $sideHeaderBorder = str_repeat($headerBorderChar, $sideLen);

        $this->writeToLogs(PHP_EOL);
        $this->writeToLogs($headerBorder);
        $this->writeToLogs($sideHeaderBorder . self::DEBUG_SUMMARY_HEADER . $sideHeaderBorder);
        $this->writeToLogs($headerBorder);

        foreach ($datas as $filename => $data) {
            $this->writeToLogs('File: ' . $filename);
            $this->writeToLogs(str_repeat('-', $longestData));
            foreach ($data as $row) {
                $this->writeToLogs($row);
            }
            $this->writeToLogs(PHP_EOL . PHP_EOL);
        }
    }

    private function debugMsgLengths($item)
    {
        $items = explode('|', $item);
        return strlen($items[1]);
    }

    private function getMicrotime()
    {
        return \DateTime::createFromFormat('U.u', microtime(true));
    }

    private function microDiff($date1, $date2){
        return number_format(abs((float)$date1->format("U.u") - (float)$date2->format("U.u")), 6);
    }

    public function writeToLogs($data = '')
    {
        if (false === file_put_contents($this->logFile, $data . PHP_EOL, FILE_APPEND | LOCK_EX)) {
            throw new \Exception('Failed to write to ' . $this->logFile);
        }
    }
}
