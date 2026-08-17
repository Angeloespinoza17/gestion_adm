<?php
namespace Database\Factories\SocialWork;
use App\Models\SocialWork\Alert;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
class AlertFactory extends Factory {
    protected $model=Alert::class;
    public function definition(): array{return ['deduplication_key'=>$this->faker->uuid(),'type'=>'seguimiento','student_profile_id'=>StudentProfile::factory(),'alerted_at'=>now(),'severity'=>'medio','reason'=>'Revisión profesional requerida.','status'=>'nueva','confidentiality'=>'interno'];}
}
