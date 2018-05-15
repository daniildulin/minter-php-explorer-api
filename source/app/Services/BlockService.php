<?php

namespace App\Services;

use GuzzleHttp\Client;
use App\Models\Block;
use App\Repository\BlockRepositoryInterface;

class BlockService implements BlockServiceInterface
{

    /** @var Client */
    private $client;

    /** @var BlockRepositoryInterface  */
    protected $blockRepository;

    /** @var TransactionServiceInterface  */
    protected $transactionService;

    /**
     * BlockService constructor.
     * @param BlockRepositoryInterface $blockRepository
     * @param TransactionServiceInterface $transactionService
     */
    public function __construct(BlockRepositoryInterface $blockRepository, TransactionServiceInterface $transactionService)
    {
        $this->client = new Client(['base_uri' => env('MINTER_API')]);

        $this->blockRepository = $blockRepository;

        $this->transactionService = $transactionService;
    }

    /**
     * Получить высоту последнего блока из API
     * @return int
     * @throws \RuntimeException
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function getLatestBlockHeight(): int
    {
        $res = $this->client->request('GET', 'status');

        $data = json_decode($res->getBody()->getContents(), 1);

        return $data['result']['sync_info']['latest_block_height'];
    }

    /**
     * Получить данные блока по высоте из API
     * @param int $blockHeight
     * @return array
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function pullBlockData(int $blockHeight): array
    {
        $res = $this->client->request('GET', 'block', [
            'query' => ['height' => $blockHeight]
        ]);

        $data = json_decode($res->getBody()->getContents(), 1);

        return $data['result'];
    }

    /**
     * Сохранить блок в базу из данных полученных через API
     * @param array $blockData
     */
    public function saveFromApiData(array $blockData): void
    {
        $block = new Block();
        $block->height     = $blockData['block']['header']['height'];
        $block->timestamp  = $blockData['block']['header']['time'];
        $block->tx_count   = $blockData['block']['header']['num_txs'];
        $block->hash       = $blockData['block_meta']['block_id']['hash'];
        $block->block_reward = $this->getBlockReward($block->height);
        $transactions  = null;

        if ($block->tx_count > 0){
            $this->transactionService->decodeTransactionsFromApiData($blockData);
            $transactions =  $this->transactionService->decodeTransactionsFromApiData($blockData);

            $block->size = $this->getBlockSize($blockData);

        } else {
            $block->size = 0;

            //TODO: Используется для теста, удалить перед коммитом
            $blockData['block']['data']['txs'] = [
                "+G4CAQGm5YpNTlQAAAAAAAAAlKkxY/3xByTcR4X/XL+5rAtZSUCfhAX14QCAG6CToeamCSCN6QyWF+C0s/PkC0qlEJ4Pxj6Wkg6Gkufl56BqJLZ23ORGZP8N0Dz2OEXI711E5R6/qOO2h0EuWA3BEg==",
                "+G4CAQGm5YpNTlQAAAAAAAAAlKkxY/3xByTcR4X/XL+5rAtZSUCfhAX14QCAG6CToeamCSCN6QyWF+C0s/PkC0qlEJ4Pxj6Wkg6Gkufl56BqJLZ23ORGZP8N0Dz2OEXI711E5R6/qOO2h0EuWA3BEg=="
            ];
            $transactions = $this->transactionService->decodeTransactionsFromApiData($blockData);
        }

        $this->blockRepository->save($block, $transactions);

    }

    /**
     * Получить размер блока
     * @param array $blockData
     * @return int
     */
    private function getBlockSize(array $blockData): int
    {
        $txs = '';

        foreach ($blockData['block']['data']['txs'] as $transaction){
            $txs .= $transaction;
        }

        return \mb_strlen($txs);
    }

    /**
     * Поучить награду за блок
     * @param int $blockHeight
     * @return int
     */
    private function getBlockReward(int $blockHeight): int
    {
        //TODO: добавить реализацию
        return 1;
    }
}