<?php

return [
    'brokers' => env('KAFKA_BROKERS', 'kafka:9092'),

    'cat_test' => [
        'async' => filter_var(env('KAFKA_TEST_ASYNC', false), FILTER_VALIDATE_BOOL),

        'async_start' => filter_var(env('KAFKA_TEST_ASYNC_START', env('KAFKA_TEST_ASYNC', false)), FILTER_VALIDATE_BOOL),
        'async_next' => filter_var(env('KAFKA_TEST_ASYNC_NEXT', env('KAFKA_TEST_ASYNC', false)), FILTER_VALIDATE_BOOL),
        'async_finish' => filter_var(env('KAFKA_TEST_ASYNC_FINISH', env('KAFKA_TEST_ASYNC', false)), FILTER_VALIDATE_BOOL),
        'poll_timeout_seconds' => (float) env('KAFKA_TEST_POLL_TIMEOUT', 25),
        'topics' => [
            'start'         => env('KAFKA_TEST_START_TOPIC', 'adaptive.test.start'),
            'next_question' => env('KAFKA_TEST_NEXT_TOPIC', 'adaptive.test.next-question'),
            'finish'        => env('KAFKA_TEST_FINISH_TOPIC', 'adaptive.test.finish'),
        ],
        'consumer_group' => env('KAFKA_TEST_CONSUMER_GROUP', 'adaptive.test'),
    ],

    'lesson' => [
        'async' => filter_var(env('KAFKA_LESSON_ASYNC', false), FILTER_VALIDATE_BOOL),

        'async_content' => filter_var(env('KAFKA_LESSON_ASYNC_CONTENT', env('KAFKA_LESSON_ASYNC', false)), FILTER_VALIDATE_BOOL),
        'async_chat' => filter_var(env('KAFKA_LESSON_ASYNC_CHAT', env('KAFKA_LESSON_ASYNC', false)), FILTER_VALIDATE_BOOL),
        'async_review' => filter_var(env('KAFKA_LESSON_ASYNC_REVIEW', env('KAFKA_LESSON_ASYNC', false)), FILTER_VALIDATE_BOOL),
        'poll_timeout_seconds' => [
            'content' => (float) env('KAFKA_LESSON_CONTENT_POLL_TIMEOUT', 90),
            'chat'    => (float) env('KAFKA_LESSON_CHAT_POLL_TIMEOUT', 25),
            'review'  => (float) env('KAFKA_LESSON_REVIEW_POLL_TIMEOUT', 45),
        ],
        'topics' => [
            'content_generate' => env('KAFKA_LESSON_CONTENT_TOPIC', 'lesson.content.generate'),
            'mentor_chat'      => env('KAFKA_LESSON_CHAT_TOPIC', 'lesson.mentor.chat'),
            'practice_review'  => env('KAFKA_LESSON_REVIEW_TOPIC', 'lesson.practice.review'),
        ],
        'consumer_group' => env('KAFKA_LESSON_CONSUMER_GROUP', 'lesson.workers'),
    ],

    'code_review' => [
        'async' => filter_var(env('KAFKA_CODE_REVIEW_ASYNC', false), FILTER_VALIDATE_BOOL),
        'async_analyze' => filter_var(env('KAFKA_CODE_REVIEW_ASYNC_ANALYZE', env('KAFKA_CODE_REVIEW_ASYNC', false)), FILTER_VALIDATE_BOOL),
        'poll_timeout_seconds' => (float) env('KAFKA_CODE_REVIEW_POLL_TIMEOUT', 60),
        'topics' => [
            'analyze' => env('KAFKA_CODE_REVIEW_TOPIC', 'chat.code-review.analyze'),
        ],
        'consumer_group' => env('KAFKA_CODE_REVIEW_CONSUMER_GROUP', 'chat.code-review'),
    ],

    'hr_interview' => [
        'async' => filter_var(env('KAFKA_HR_INTERVIEW_ASYNC', false), FILTER_VALIDATE_BOOL),
        'async_reply' => filter_var(env('KAFKA_HR_INTERVIEW_ASYNC_REPLY', env('KAFKA_HR_INTERVIEW_ASYNC', false)), FILTER_VALIDATE_BOOL),
        'async_code' => filter_var(env('KAFKA_HR_INTERVIEW_ASYNC_CODE', env('KAFKA_HR_INTERVIEW_ASYNC', false)), FILTER_VALIDATE_BOOL),
        'poll_timeout_seconds' => (float) env('KAFKA_HR_INTERVIEW_POLL_TIMEOUT', 45),
        'topics' => [
            'chat' => env('KAFKA_HR_INTERVIEW_TOPIC', 'chat.hr-interview.chat'),
        ],
        'consumer_group' => env('KAFKA_HR_INTERVIEW_CONSUMER_GROUP', 'chat.hr-interview'),
    ],
];
