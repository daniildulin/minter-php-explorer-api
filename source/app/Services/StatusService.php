<?php

namespace App\Services;


use App\Models\Block;
use Illuminate\Support\Facades\Cache;

class StatusService implements StatusServiceInterface
{

    /**
     * Получить высоту последнего блока
     * @return int
     */
    public function getLastBlockHeight(): int
    {
        $height = Cache::get('latest_block_height');

        if (!$height){
            return Block::orderBy('created_at', 'desc')->first()->height;
        }

        return $height;
    }

    /**
     * Получить количество транзакций в секунду
     * @return int
     */
    public function getTransactionsPerSecond(): int
    {
        //TODO: добавить реализацию
        return 1;
    }

    /**
     * Получить среднее время обработки блока в секундах
     * @return int
     */
    public function getAverageBlockTime(): int
    {
        //TODO: добавить реализацию
        return 1;
    }

}