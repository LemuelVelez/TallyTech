<?php

namespace App\Controllers;

class SportsController extends BaseController
{
    public function index()
    {
        $data = $this->scoringService()->commonData();
        $data['title'] = 'Sports';
        $data['sportCategories'] = $this->repository()->sportCategories(true);
        $data['weightedPoints'] = $this->repository()->weightedPoints((int)($data['activeEvent']['id']??0));
        return view('sports/index', $data);
    }

    public function store()
    {
        $event = $this->repository()->activeEvent();
        if (! $event) {
            return redirect()->back()->with('error', 'Add and activate an event first.');
        }
        $payload = $this->sportPayload((int) $event['id']);
        if (isset($payload['error'])) {
            return redirect()->back()->withInput()->with('error', $payload['error']);
        }
        $payload['created_at'] = date('Y-m-d H:i:s');
        try {
            $this->repository()->createSport($payload, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->withInput()->with('error', $this->safeErrorMessage($e, 'Sport already exists for this event or could not be created.'));
        }
        return redirect()->back()->with('success', 'Sport added.');
    }

    public function update(int $id)
    {
        $event = $this->repository()->activeEvent();
        if (! $event) {
            return redirect()->back()->with('error', 'No active event.');
        }
        $payload = $this->sportPayload((int) $event['id']);
        if (isset($payload['error'])) {
            return redirect()->back()->with('error', $payload['error']);
        }
        try {
            $this->repository()->updateSport($id, $payload, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The sport operation could not be completed.'));
        }
        return redirect()->back()->with('success', 'Sport updated.');
    }

    public function delete(int $id)
    {
        try {
            $this->repository()->deleteSport($id, (int) session()->get('user_id'));
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $this->safeErrorMessage($e, 'The sport operation could not be completed.'));
        }
        return redirect()->back()->with('success', 'Sport removed.');
    }

    private function sportPayload(int $eventId): array
    {
        $name=trim($this->postString('name'));$categoryId=$this->postPositiveInt('category_id');$type=$this->postString('result_type');
        if($name===''||mb_strlen($name)>120||!$categoryId||!in_array($type,['match','judged'],true))return ['error'=>'Complete all sport fields.'];
        $points=[];foreach(['first_points','second_points','third_points','fourth_points','participation_points'] as $field){$raw=trim($this->postString($field));if($raw===''||!preg_match('/^(?:0|[1-9]\d*)(?:\.\d{1,2})?$/',$raw)||(float)$raw>999999.99)return ['error'=>'Enter valid weighted points for all placements.'];$points[$field]=number_format((float)$raw,2,'.','');}
        return ['event_id'=>$eventId,'name'=>$name,'category_id'=>$categoryId,'result_type'=>$type,'weighted_points'=>$points];
    }

}
