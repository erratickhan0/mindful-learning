<?php

namespace App\Services;

class LearningContentService
{
    public function __construct(protected OpenAiLearningService $openAiLearningService)
    {
    }

    public function generate(string $subject, string $ageGroup, string $type, string $difficulty): array
    {
        $aiContent = $this->openAiLearningService->generateContent($subject, $ageGroup, $type, $difficulty);
        if ($this->isValidAiContent($aiContent, $type)) {
            return [
                'type' => $type,
                'subject' => $subject,
                'difficulty' => $difficulty,
                'title' => $aiContent['title'],
                'prompt' => $aiContent['prompt'],
                'options' => $type === 'activity' ? [] : ($aiContent['options'] ?? []),
                'answer' => $type === 'activity' ? '' : $aiContent['answer'],
                'hint' => $type === 'activity' ? '' : ($aiContent['hint'] ?? ''),
                'source' => 'openai',
            ];
        }

        $content = $this->library()[$subject][$ageGroup][$type];
        $selected = $content[array_rand($content)];

        if ($type === 'activity') {
            return [
                'type' => $type,
                'subject' => $subject,
                'difficulty' => $difficulty,
                'title' => $selected['title'],
                'prompt' => $selected['prompt'],
                'source' => 'local',
            ];
        }

        return [
            'type' => $type,
            'subject' => $subject,
            'difficulty' => $difficulty,
            'title' => $selected['title'],
            'prompt' => $selected['prompt'],
            'options' => $selected['options'] ?? [],
            'answer' => $selected['answer'],
            'hint' => $selected['hint'],
            'source' => 'local',
        ];
    }

    protected function isValidAiContent(?array $content, string $type): bool
    {
        if (! is_array($content)) {
            return false;
        }

        if (! isset($content['title'], $content['prompt'])) {
            return false;
        }

        if ($type === 'activity') {
            return true;
        }

        return isset($content['answer']) && is_array($content['options']);
    }

    protected function library(): array
    {
        return [
            'math' => [
                '4-6' => [
                    'quiz' => [
                        ['title' => 'Number Friends', 'prompt' => 'What comes after 9?', 'options' => ['8', '10', '11'], 'answer' => '10', 'hint' => 'Count one step ahead.'],
                        ['title' => 'Tiny Add', 'prompt' => '2 + 3 = ?', 'options' => ['4', '5', '6'], 'answer' => '5', 'hint' => 'Use fingers if needed.'],
                    ],
                    'puzzle' => [
                        ['title' => 'Shape Count', 'prompt' => 'How many sides does a square have?', 'options' => ['3', '4', '5'], 'answer' => '4', 'hint' => 'Think of a box shape.'],
                        ['title' => 'Missing Number', 'prompt' => '1, 2, __, 4', 'options' => ['3', '5', '6'], 'answer' => '3', 'hint' => 'Count in order.'],
                    ],
                    'activity' => [
                        ['title' => 'Toy Counting', 'prompt' => 'Collect 10 toys and group them by color.'],
                        ['title' => 'Hop Math', 'prompt' => 'Hop and count from 1 to 20 out loud.'],
                    ],
                ],
                '7-9' => [
                    'quiz' => [
                        ['title' => 'Speed Add', 'prompt' => '14 + 8 = ?', 'options' => ['20', '22', '24'], 'answer' => '22', 'hint' => 'Break 8 into 6 and 2.'],
                        ['title' => 'Compare', 'prompt' => 'Which is greater?', 'options' => ['37', '73', 'same'], 'answer' => '73', 'hint' => 'Compare tens first.'],
                    ],
                    'puzzle' => [
                        ['title' => 'Pattern Grid', 'prompt' => '5, 10, 15, __', 'options' => ['18', '20', '25'], 'answer' => '20', 'hint' => 'Add 5 each time.'],
                        ['title' => 'Split Team', 'prompt' => '12 apples into 3 equal groups = ?', 'options' => ['3', '4', '5'], 'answer' => '4', 'hint' => '12 divided by 3.'],
                    ],
                    'activity' => [
                        ['title' => 'Mini Shop', 'prompt' => 'Make a pretend shop and add 5 item prices.'],
                        ['title' => 'Measure Hunt', 'prompt' => 'Measure table, bed, and book with hand spans.'],
                    ],
                ],
                '10-12' => [
                    'quiz' => [
                        ['title' => 'Fraction Sense', 'prompt' => 'Which is larger?', 'options' => ['1/3', '1/2', 'Equal'], 'answer' => '1/2', 'hint' => 'Same numerator, smaller denominator is larger part.'],
                        ['title' => 'Multiply Smart', 'prompt' => '12 x 6 = ?', 'options' => ['62', '72', '82'], 'answer' => '72', 'hint' => '12 x 3 then double.'],
                    ],
                    'puzzle' => [
                        ['title' => 'Equation Puzzle', 'prompt' => '3x + 4 = 19. x = ?', 'options' => ['3', '5', '7'], 'answer' => '5', 'hint' => 'Subtract 4 then divide by 3.'],
                        ['title' => 'Ratio Match', 'prompt' => '2:3 = 8:__', 'options' => ['10', '12', '14'], 'answer' => '12', 'hint' => 'Multiply both sides by 4.'],
                    ],
                    'activity' => [
                        ['title' => 'Budget Challenge', 'prompt' => 'Plan a 500 budget for a class snack party.'],
                        ['title' => 'Data Detective', 'prompt' => 'Survey 5 people and chart favorite fruits.'],
                    ],
                ],
            ],
            'english' => [
                '4-6' => [
                    'quiz' => [
                        ['title' => 'Letter Fun', 'prompt' => 'Which starts with B?', 'options' => ['ball', 'cat', 'sun'], 'answer' => 'ball', 'hint' => 'Listen to first sound.'],
                        ['title' => 'Rhyme Time', 'prompt' => 'Word that rhymes with log?', 'options' => ['dog', 'pen', 'cup'], 'answer' => 'dog', 'hint' => 'Same ending sound.'],
                    ],
                    'puzzle' => [
                        ['title' => 'Word Build', 'prompt' => 'Unscramble: TAC', 'options' => ['CAT', 'ACT', 'CUT'], 'answer' => 'CAT', 'hint' => 'It is a pet animal.'],
                        ['title' => 'Story Fill', 'prompt' => 'The bird can __', 'options' => ['fly', 'swim', 'drive'], 'answer' => 'fly', 'hint' => 'Bird wings help it.'],
                    ],
                    'activity' => [
                        ['title' => 'Picture Story', 'prompt' => 'Draw 3 pictures and tell a mini story.'],
                        ['title' => 'Sound Hunt', 'prompt' => 'Find 5 objects starting with S.'],
                    ],
                ],
                '7-9' => [
                    'quiz' => [
                        ['title' => 'Grammar Check', 'prompt' => 'Choose correct sentence:', 'options' => ['i am happy.', 'I am happy.', 'i Am happy.'], 'answer' => 'I am happy.', 'hint' => 'Capitalize sentence start and I.'],
                        ['title' => 'Meaning Match', 'prompt' => 'Opposite of bright?', 'options' => ['dark', 'light', 'shine'], 'answer' => 'dark', 'hint' => 'Think of no light.'],
                    ],
                    'puzzle' => [
                        ['title' => 'Verb Puzzle', 'prompt' => 'Past tense of play?', 'options' => ['plays', 'played', 'playing'], 'answer' => 'played', 'hint' => 'Usually add ed.'],
                        ['title' => 'Context Clue', 'prompt' => 'A place with many books is a __', 'options' => ['library', 'kitchen', 'market'], 'answer' => 'library', 'hint' => 'You can borrow books there.'],
                    ],
                    'activity' => [
                        ['title' => 'Comic Writer', 'prompt' => 'Create a 4-panel comic with dialogue.'],
                        ['title' => 'Read Aloud', 'prompt' => 'Read one paragraph and record your voice.'],
                    ],
                ],
                '10-12' => [
                    'quiz' => [
                        ['title' => 'Inference Quiz', 'prompt' => 'If clouds darken and wind rises, what is likely?', 'options' => ['A storm', 'A holiday', 'A rainbow now'], 'answer' => 'A storm', 'hint' => 'Use context clues.'],
                        ['title' => 'Parts of Speech', 'prompt' => 'Identify adjective:', 'options' => ['run', 'beautiful', 'table'], 'answer' => 'beautiful', 'hint' => 'It describes noun quality.'],
                    ],
                    'puzzle' => [
                        ['title' => 'Sentence Repair', 'prompt' => 'Fix: "he dont likes apples"', 'options' => ['He does not like apples.', 'He dont like apples.', 'He does not likes apples.'], 'answer' => 'He does not like apples.', 'hint' => 'Use does not + base verb.'],
                        ['title' => 'Theme Spot', 'prompt' => 'A story about never giving up has theme of __', 'options' => ['perseverance', 'confusion', 'silence'], 'answer' => 'perseverance', 'hint' => 'Keep trying theme.'],
                    ],
                    'activity' => [
                        ['title' => 'Debate Starter', 'prompt' => 'Write 5 points for and against school uniforms.'],
                        ['title' => 'Creative Rewrite', 'prompt' => 'Rewrite a fairy tale ending in your own style.'],
                    ],
                ],
            ],
            'science' => [
                '4-6' => [
                    'quiz' => [
                        ['title' => 'Living or Not', 'prompt' => 'Which is living?', 'options' => ['rock', 'tree', 'chair'], 'answer' => 'tree', 'hint' => 'Living things grow.'],
                        ['title' => 'Body Basics', 'prompt' => 'We see with our __', 'options' => ['eyes', 'ears', 'hands'], 'answer' => 'eyes', 'hint' => 'Used for looking.'],
                    ],
                    'puzzle' => [
                        ['title' => 'Weather Word', 'prompt' => 'Dark clouds often mean __', 'options' => ['rain', 'snow always', 'sunshine'], 'answer' => 'rain', 'hint' => 'Think monsoon.'],
                        ['title' => 'Animal Home', 'prompt' => 'Fish live in __', 'options' => ['water', 'sand', 'sky'], 'answer' => 'water', 'hint' => 'Aquatic life.'],
                    ],
                    'activity' => [
                        ['title' => 'Plant Buddy', 'prompt' => 'Water a small plant and draw it each day.'],
                        ['title' => 'Shadow Play', 'prompt' => 'Use torch and toys to make shadow animals.'],
                    ],
                ],
                '7-9' => [
                    'quiz' => [
                        ['title' => 'Matter Check', 'prompt' => 'Water becomes ice when it gets __', 'options' => ['hot', 'cold', 'dirty'], 'answer' => 'cold', 'hint' => 'Freezing happens at low temperature.'],
                        ['title' => 'Plant Needs', 'prompt' => 'Plants need sunlight, water and __', 'options' => ['air', 'plastic', 'paper'], 'answer' => 'air', 'hint' => 'Gas exchange.'],
                    ],
                    'puzzle' => [
                        ['title' => 'Lifecycle', 'prompt' => 'Egg -> caterpillar -> __ -> butterfly', 'options' => ['pupa', 'worm', 'moth'], 'answer' => 'pupa', 'hint' => 'Third stage.'],
                        ['title' => 'Sense Match', 'prompt' => 'We hear with __', 'options' => ['nose', 'ears', 'tongue'], 'answer' => 'ears', 'hint' => 'Used for listening.'],
                    ],
                    'activity' => [
                        ['title' => 'Sink or Float', 'prompt' => 'Test 8 objects and make a result table.'],
                        ['title' => 'Weather Log', 'prompt' => 'Track weather for 5 days and identify patterns.'],
                    ],
                ],
                '10-12' => [
                    'quiz' => [
                        ['title' => 'Forces', 'prompt' => 'Object keeps moving due to __', 'options' => ['inertia', 'gravity only', 'temperature'], 'answer' => 'inertia', 'hint' => 'Newton first law.'],
                        ['title' => 'Ecosystem', 'prompt' => 'Producers in food chain are mostly __', 'options' => ['plants', 'animals', 'fungi only'], 'answer' => 'plants', 'hint' => 'They make food using sunlight.'],
                    ],
                    'puzzle' => [
                        ['title' => 'Energy Form', 'prompt' => 'Solar panel converts sunlight into __ energy', 'options' => ['electrical', 'sound', 'chemical'], 'answer' => 'electrical', 'hint' => 'Used to run appliances.'],
                        ['title' => 'System Match', 'prompt' => 'Which organ pumps blood?', 'options' => ['lungs', 'heart', 'liver'], 'answer' => 'heart', 'hint' => 'Core of circulatory system.'],
                    ],
                    'activity' => [
                        ['title' => 'Mini Experiment', 'prompt' => 'Create a simple water filter using sand, gravel, cotton.'],
                        ['title' => 'Food Web Map', 'prompt' => 'Draw a local ecosystem food web with 6 organisms.'],
                    ],
                ],
            ],
        ];
    }
}
