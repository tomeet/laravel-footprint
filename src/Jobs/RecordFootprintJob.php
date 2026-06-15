<?php

namespace Tomeet\Laravel\Footprint\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RecordFootprintJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        protected Model $user,
        protected Model $object,
        protected array $snapshot = []
    ) {}

    public function handle(): void
    {
        try {
            $this->user->recordFootprint($this->object, $this->snapshot);
        } catch (\Exception $e) {
            \Log::warning('Footprint queue job failed: ' . $e->getMessage(), [
                'user_id' => $this->user->getKey(),
                'object_type' => get_class($this->object),
                'object_id' => $this->object->getKey(),
            ]);
        }
    }
}
