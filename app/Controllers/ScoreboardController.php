<?php
namespace App\Controllers;
class ScoreboardController extends BaseController
{ public function index(){ $d=$this->scoringService()->scoreboard(); $d['title']='Live Scoreboard'; return view('scoreboard/index',$d); } }
