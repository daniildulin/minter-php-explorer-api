<?php

namespace App\Services;

use App\Helpers\DateTimeHelper;
use App\Models\Block;
use App\Repository\BlockRepositoryInterface;
use Carbon\Carbon;
use DateTimeZone;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Minter\SDK\MinterReward;

class BlockService implements BlockServiceInterface
{

    protected const DEFAULT_BLOCK_TIME = 5;

    /** @var BlockRepositoryInterface */
    protected $blockRepository;
    /** @var TransactionServiceInterface */
    protected $transactionService;
    /** @var Client */
    protected $client;
    /** @var ValidatorServiceInterface */
    protected $validatorService;

    /**
     * BlockService constructor.
     * @param BlockRepositoryInterface $blockRepository
     * @param TransactionServiceInterface $transactionService
     * @param ValidatorServiceInterface $validatorService
     * @param Client $client
     */
    public function __construct(
        BlockRepositoryInterface $blockRepository,
        TransactionServiceInterface $transactionService,
        ValidatorServiceInterface $validatorService,
        Client $client
    ) {
        $this->client = $client;

        $this->blockRepository = $blockRepository;

        $this->transactionService = $transactionService;

        $this->validatorService = $validatorService;
    }

    /**
     * Получить высоту последнего блока из API
     * @return int
     * @throws \RuntimeException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getLatestBlockHeight(): int
    {
        $res = $this->client->request('GET', 'api/status');

        $data = json_decode($res->getBody()->getContents(), 1);

        return $data['result']['latest_block_height'];
    }

    /**
     * Получить данные блока по высоте из API
     * @param int $blockHeight
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function pullBlockData(int $blockHeight): array
    {
        $res = $this->client->request('GET', "api/block/{$blockHeight}");

        $data = json_decode($res->getBody()->getContents(), 1);

        return $data['result'];
    }

    /**
     * Сохранить блок в базу из данных полученных через API
     * @param array $blockData
     */
    public function saveFromApiData(array $blockData): void
    {
        if (!$blockData) {
            return;
        }

        $blockTime = DateTimeHelper::getDateTimeFromNanoSeconds($blockData['time']);

        $block = new Block();
        $block->height = $blockData['height'];
        $block->timestamp = DateTimeHelper::getDateTimeAsFloat($blockData['time']);
        $block->created_at = $blockTime->format('Y-m-d H:i:sO');
        $block->tx_count = $blockData['num_txs'];
        $block->hash = 'Mh' . mb_strtolower($blockData['hash']);
        $block->block_reward = $this->getBlockReward($block->height);
        $block->block_time = $this->calculateBlockTime($block->timestamp);

        $transactions = null;
        $validators = null;
        $tags = [];

        if ($block->tx_count > 0) {
            $transactions = $this->transactionService->decodeTransactionsFromApiData($blockData);
            $block->size = $this->getBlockSize($blockData);
            $tags = $this->transactionService->decodeTxTagsFromApiData($blockData);
        } else {
            $block->size = 0;
        }

        $validators = $this->validatorService->saveValidatorsFromApiData($block->height);

        $this->blockRepository->save($block, $transactions, $validators);

        if(\count($tags)){
            $this->transactionService->saveTransactionsTags($tags);
        }

        $expiresAt = new \DateTime();
        try {
            $expiresAt->add(new \DateInterval('PT4S'));
        } catch (\Exception $e) {
        }

        Cache::forget('last_block_time');
        Cache::forget('last_block_height');

        Cache::put('last_block_time', $blockTime->getTimestamp(), 1);
        Cache::put('last_block_height', $block->height, $expiresAt);
    }

    /**
     * Поучить награду за блок
     * @param int $blockHeight
     * @return string
     */
    private function getBlockReward(int $blockHeight): string
    {
        return MinterReward::get($blockHeight);
    }

    /**
     * @param Carbon $currentBlockTime
     * @return float
     */
    private function calculateBlockTime(string $currentBlockTime): float
    {
        $lastBlock = Block::orderBy('created_at', 'desc')->limit(1)->first();

        if (!$lastBlock) {
            return $this::DEFAULT_BLOCK_TIME;
        }

        return (float)$currentBlockTime - (float)$lastBlock->timestamp;
    }

    /**
     * Получить размер блока
     * @param array $blockData
     * @return int
     */
    private function getBlockSize(array $blockData): int
    {
        //TODO: получать из API

        return 0;
    }

    /**
     * Получить высоту последнего блока из Базы
     * @return int
     */
    public function getExplorerLatestBlockHeight(): int
    {
        $block = Block::orderByDesc('height')->first();

        return $block->height ?? 0;
    }

    /**
     * Скорость обработки блоков за последние 24 часа
     * @return float
     */
    public function blockSpeed24h(): float
    {
        $blocks = $this->blockRepository->getBlocksCountByPeriod(86400);

        return round($blocks / 86400, 8);
    }
}