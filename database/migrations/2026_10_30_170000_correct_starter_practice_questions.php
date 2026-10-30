<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $carbocation = DB::table('questions')
            ->where('topic_name', 'Carbocations & Hyperconjugation')
            ->where('stem', 'like', 'Arrange the following carbocations in order of decreasing stability%')
            ->where('review_status', 'pending_review')
            ->where('is_pyq', false)
            ->first();

        if ($carbocation) {
            DB::table('questions')->where('id', $carbocation->id)->update([
                'correct_option' => 'B',
                'explanation' => 'The benzyl carbocation is resonance-stabilized and is more stable than the tertiary butyl carbocation. The ethyl carbocation is less stable, and the vinyl carbocation is least stable because its positive charge is on an sp2 carbon.',
                'updated_at' => now(),
            ]);
        }

        $cannizzaro = DB::table('questions')
            ->where('topic_name', 'Aldol & Cannizzaro Reactions')
            ->where('stem', 'like', 'Which of the following carbonyl compounds will give BOTH a positive iodoform test%')
            ->where('review_status', 'pending_review')
            ->where('is_pyq', false)
            ->first();

        if (! $cannizzaro) return;

        $stem = 'Which aldehyde has no alpha hydrogen and therefore undergoes the Cannizzaro reaction with concentrated NaOH?';
        $options = [
            ['id' => 'A', 'text' => 'Acetaldehyde (CH3CHO)'],
            ['id' => 'B', 'text' => '2,2-Dimethylpropanal ((CH3)3C-CHO)'],
            ['id' => 'C', 'text' => 'Propanal (CH3CH2CHO)'],
            ['id' => 'D', 'text' => '2-Methylpropanal ((CH3)2CHCHO)'],
        ];
        $fingerprint = hash('sha256', Str::lower(Str::squish($stem)).'|'.json_encode($options, JSON_UNESCAPED_UNICODE));

        DB::table('questions')->where('id', $cannizzaro->id)->update([
            'stem' => $stem,
            'options' => json_encode($options, JSON_UNESCAPED_UNICODE),
            'correct_option' => 'B',
            'explanation' => '2,2-Dimethylpropanal has no hydrogen on the carbon next to the aldehyde group, so it undergoes the Cannizzaro reaction. The other listed aldehydes have alpha hydrogens.',
            'content_fingerprint' => $fingerprint,
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Keep verified answer corrections when rolling back unrelated schema work.
    }
};
