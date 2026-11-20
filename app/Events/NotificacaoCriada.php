<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Notificacao;

class NotificacaoCriada implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $notificacao;

    public function __construct(Notificacao $notificacao)
    {
        $this->notificacao = $notificacao;
    }

    public function broadcastOn(): array
    {
        // Canal privado para o utilizador específico
        return [
            new PrivateChannel('App.Models.User.' . $this->notificacao->user_id),
        ];
    }
    
    // Opcional: definir o nome do evento no frontend
    public function broadcastAs(): string
    {
        return 'notificacao.criada';
    }
}