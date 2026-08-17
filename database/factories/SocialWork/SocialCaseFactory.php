<?php
namespace Database\Factories\SocialWork;
use App\Models\SocialWork\SocialCase;
use App\Models\StudentProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
class SocialCaseFactory extends Factory {
    protected $model=SocialCase::class;
    public function definition(): array{return ['code'=>'TS-'.now()->year.'-'.$this->faker->unique()->numerify('#####'),'primary_student_id'=>StudentProfile::factory(),'title'=>$this->faker->sentence(5),'reason'=>$this->faker->sentence(),'opened_on'=>today(),'priority'=>'media','risk_level'=>'sin_evaluar','confidentiality'=>'restringido','status'=>'borrador'];}
}
