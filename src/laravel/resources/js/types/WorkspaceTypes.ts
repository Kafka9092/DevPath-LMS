
 
export interface Subtopic {
    id: number;
    title: string;
    order: number;
    theme_id: number;
}
 
export interface Theme {
    id: number;
    title: string;
    order: number;
    module_id: number;
    subtopics: Subtopic[];
}
 
export interface Module {
    id: number;
    title: string;
    module_number: number;
    course_id: number;
    themes: Theme[];
}
 
export interface Course {
    id: number;
    title: string;
    modules: Module[];
}
 
export interface ExerciseHistoryItem {
    subtopic_id: number;
    subtopic_title: string;
    theme_title: string;
    completed_at: string;
    task_title?: string | null;
    task_description?: string | null;
    submitted_code?: string | null;
    lesson_score?: number | null;
    lesson_feedback?: string | null;
}
  
export type BlockType = 'theory' | 'task' | 'answer' | 'hint' | 'error' | 'solution_feedback' | 'user_message' | 'system' | 'lesson_review' | 'mentor' | 'micro_task' | 'mentor_prompt' | 'mentor_menu';

export type MentorActionId = 'accept' | 'decline';
export type MentorOptionId = 'concept' | 'review_code' | 'architecture' | 'first_steps';

export interface MentorAction {
    id: MentorActionId;
    label: string;
}

export interface MentorMenuOption {
    id: MentorOptionId;
    label: string;
    icon?: 'map' | 'squares' | 'rocket' | 'magnifier';
}

export type HintType = 'code_snippet' | 'explanation' | 'simpler_solution' | 'skeleton_hint';

export type RightPanelMode = 'playground' | 'trace' | 'none';

export interface TheoryPlayground {
    code: string;
    language: string;
    stdin?: string;
}

export interface TraceStep {
    line: number;
    highlight: string;
    memory?: { name: string; value: string }[];
}

export interface ChatBlock {
    id: string;
    type: BlockType;
    content?: string;
    has_more?: boolean;
    part?: number;
    total_parts?: number;
    slide_title?: string;
    callout?: string | null;
    right_panel_mode?: RightPanelMode;
    playground?: TheoryPlayground | null;
    trace_steps?: TraceStep[] | null;
    mentor_message?: string | null;
    personalization_label?: string | null;
    title?: string;
    description?: string;
    starter_code?: string;
    action?: string;
    function_signature?: string;
    constraints?: string[];
    example_input?: string;
    example_output?: string;
    test_cases?: { label?: string; input: string; output: string }[];
    intro_message?: string | null;
    is_correct?: boolean;
    score?: number;
    feedback?: string;
    strengths?: string[];
    improvements?: string[];
    message?: string;
    hint_type?: HintType;
    review_mode?: boolean;
    lesson_completed?: boolean;
    submitted_code?: string;
    task_title?: string;
    task_description?: string;
    actions?: MentorAction[];
    menu_options?: MentorMenuOption[];
}
 
export type LessonPhase =
    | 'idle'
    | 'generating'
    | 'learning'
    | 'theory_review'
    | 'task'
    | 'evaluating'
    | 'completed'
    | 'lesson_review';
