<?php

namespace App\Jobs;

use App\Enums\StatusNumberEnum;
use App\Models\CodeNumberState;
use App\Services\NumberService;
use App\Services\NumberStateService;
use App\Telegram\Traits\Support;
use DefStudio\Telegraph\Facades\Telegraph;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DeactivateNumberJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Support;

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
            $text_with = $this->getTextForMsg('text_deactivate_number_jobs');
            $text_with = str_replace('#number#', $this->number, $text_with);
            Telegraph::chat($numberState->seller_id)
                ->message($text_with)->send();
            $numberState->delete();
            $numberModel = $numberService->getNumber($this->number);
            $numberModel->update(['status_number' => StatusNumberEnum::failed]);
            $text_deactivate = $this->getTextForMsg('text_deactivate_number');
            $text_deactivate = str_replace('#number#', $this->number, $text_deactivate);
            Telegraph::chat($this->buyer_id)
                ->message($text_deactivate)->send();
        }else
        {
            Log::info("Номер {$this->number} не попал под временной отрезок");
        }
    }
}
