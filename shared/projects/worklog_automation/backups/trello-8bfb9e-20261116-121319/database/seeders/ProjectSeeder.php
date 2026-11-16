<?php

namespace Database\Seeders;

use App\Models\ProjectFor;
use Illuminate\Database\Seeder;

class ProjectSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'Avaib' => [
                'Forco'  => [],
                'Chris Bell'    => [],
                'QA Automation'    => [],
                'QA Training '    => [],
                'AI Automation'    => [],
                'Social Media Posting'    => [],
                'ConsensusAI'    => [],
                'Holidays Notification'    => [],
                'Linkedin Automation for personalized outreach'    => [],
                'Avaib-redesign by Forco'  => [],
                'Task Assignment and follow-ups'  => [],
                'Avaib'  => [],
                'Skills Development'  => [],
                'No Project / Work'  => [],
            ],
            'QC' => [
                'Quick Task' => [],
                'QC Shopify' => [],
                'QC' => [],
                'Outbound calling for arrange appointment - QC' => []
            ],
            'Avaib & Remoteface' => [
                'Renter Vibe' => [],
            ],
            'TradeSora' => [
                'TradeSora' => [],
            ],
        ];

        foreach ($data as $pfName => $projects) {
            $pf = ProjectFor::firstOrCreate(['name' => $pfName]);

            foreach ($projects as $projectName => $tasks) {
                $project = $pf->projects()->firstOrCreate(['name' => $projectName]);
            }
        }
    }
}
