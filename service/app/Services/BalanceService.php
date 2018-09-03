<?php

namespace App\Services;


use App\Helpers\StringHelper;
use App\Models\Balance;
use App\Models\BalanceChannel;
use App\Models\Coin;
use App\Repository\BalanceRepositoryInterface;
use Illuminate\Support\Collection;

class BalanceService implements BalanceServiceInterface
{
    /** @var BalanceRepositoryInterface  */
    protected $balanceRepository;

    /** @var \phpcent\Client */
    protected $centrifuge;

    /**
     * BalanceService constructor.
     * @param BalanceRepositoryInterface $balanceRepository
     * @param \phpcent\Client $centrifuge
     */
    public function __construct(BalanceRepositoryInterface $balanceRepository, \phpcent\Client $centrifuge)
    {
        $this->balanceRepository = $balanceRepository;
        $this->centrifuge = $centrifuge;
    }

    /**
     * Получить баланс адреса
     * @param string $address
     * @return Collection
     */
    public function getAddressBalance(string $address): Collection
    {
        $result = $this->balanceRepository->getBalanceByAddress($address)->map(function ($item) {

            $coin = new Coin($item->coin, $item->amount);
            return [
                'coin' => $coin->getName(),
                'amount' => $coin->getAmount(),
                'baseCoinAmount' => $coin->getAmount(),
                'usdAmount' => $coin->getUsdAmount(),
            ];

        });
        return $result;
    }

    /**
     * @param string $address
     * @param array $data
     * @return Collection
     */
    public function updateAddressBalanceFromAipData(string $address, array $data): Collection
    {
        $balances = [];
        foreach ($data as $coin => $val){
            $balances[] = $this->balanceRepository->updateByAddressAndCoin($address, $coin, $val);
        }
        return collect($balances);
    }

    /**
     * Inform about balance change via WS
     * @param Collection $balances
     */
    public function broadcastNewBalances(Collection $balances): void
    {
        $balances->each(function($balance){
            /** @var Balance $balance */
            $channels = $this->balanceRepository->getChannelsForBalanceAddress($balance->address);

            $channels->each(function ($channel) use($balance){
                /** @var BalanceChannel $channel */
                $this->centrifuge->publish($channel->name,[
                    'address' => StringHelper::mb_ucfirst($balance->address),
                    'coin' => mb_strtoupper($balance->coin),
                    'amount' => $balance->amount,
                ]);
            });

        });

        $this->balanceRepository->deleteOldChannels();
    }
}