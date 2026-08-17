<?php
namespace App\Notifications\SocialWork;
use App\Models\SocialWork\Alert;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
class SocialWorkAlertNotification extends Notification {
    use Queueable;
    public function __construct(private readonly Alert $alert) {}
    public function via(object $notifiable): array{return ['database'];}
    public function toArray(object $notifiable): array{return ['title'=>'Alerta de Trabajo Social','message'=>'Existe una alerta social asignada que requiere revisión.','icon'=>'bx bx-bell','priority'=>$this->alert->severity,'action_url'=>'/social-work/alerts','alert_id'=>$this->alert->id,'status'=>$this->alert->status];}
}
