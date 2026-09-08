<?php

namespace App\Controllers;

class DraftGeneratorController extends BaseController
{
    public function index()
    {
        $data = $this->scoringService()->commonData();
        $data['title'] = 'Draft Generator';

        $sportGroups = [];
        foreach ($data['sports'] as $sport) {
            $name = trim((string) ($sport['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            if (! isset($sportGroups[$name])) {
                $sportGroups[$name] = [
                    'name' => $name,
                    'categories' => [],
                ];
            }

            $category = trim((string) ($sport['category'] ?? ''));
            if ($category !== '' && ! in_array($category, $sportGroups[$name]['categories'], true)) {
                $sportGroups[$name]['categories'][] = $category;
            }
        }

        foreach ($sportGroups as &$group) {
            sort($group['categories']);
        }
        unset($group);

        $data['draftSportGroups'] = array_values($sportGroups);

        return view('draft_generator/index', $data);
    }
}
