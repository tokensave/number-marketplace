<?php

namespace App\Jobs;

use App\Enums\StatusNumberEnum;
use App\Models\CodeNumberState;
use App\Services\NumberService;
use App\Services\NumberStateService;
use DefStudio\Telegraph\Facades\Telegraph;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeactivateNumberJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private string $number;
    private string $buyer_id;

    public function __construct(string $number, string $buyer_id)
    {
        $this->number = $number;
        $this->buyer_id = $buyer_id;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $codeNumberService = new NumberStateService();
        $numberService = new NumberService();
        $numberState = $codeNumberService->getPendingCodeNumber($this->number, $this->buyer_id);
        if ($numberState) {
            Telegraph::chat($numberState->seller_id)
                ->message("<b>Номер {$this->number} деактивирован. Прошло более 2 минут</b>")->send();
            $numberState->delete();
            $numberModel = $numberService->getNumber($this->number);
            $numberModel->update(['status_number' => StatusNumberEnum::failed]);
            Telegraph::chat($this->buyer_id)
                ->message("<b>Номер {$this->number} деактивирован.</b>")->send();
        }else
        {
            Log::info("Номер {$this->number} не попал под временной отрезок");
        }
    }
}
