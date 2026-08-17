<?php
namespace App\Models\SocialWork;
class JunaebDeliveryItem extends SocialWorkModel { protected $table = 'junaeb_delivery_items'; protected $casts = ['quantity' => 'decimal:2']; }
