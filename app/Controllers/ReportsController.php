<?php
namespace App\Controllers;
class ReportsController extends BaseController
{ public function index(){return view('reports/index',['title'=>'Reports','report'=>$this->repository()->reportSummary()]);} }
