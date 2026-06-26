export interface Task {
    id: string;
    title: string;
    description: string;
    status: 'completed' | 'in_progress' | 'locked';
    maxScore?: number;
    userScore?: number;
    type?: 'quiz' | 'code' | 'theory';
}

interface TaskPanelProps {
    subtopicTitle: string;
    tasks: Task[];
    onTaskClick?: (task: Task) => void;
}

export default function TaskPanel({ subtopicTitle, tasks, onTaskClick }: TaskPanelProps) {
    const completedCount = tasks.filter(t => t.status === 'completed').length;
    const totalScore = tasks.reduce((sum, t) => sum + (t.userScore || 0), 0);
    const maxTotalScore = tasks.reduce((sum, t) => sum + (t.maxScore || 0), 0);

    return (
        <div>
            {}
            <div>
                <h3>{subtopicTitle || 'Выберите тему'}</h3>

                {subtopicTitle && (
                    <div>
                        <div>
                            <span>Прогресс:</span>
                            <span>{completedCount} / {tasks.length}</span>
                        </div>
                        {maxTotalScore > 0 && (
                            <div>
                                <span>Очки:</span>
                                <span>{totalScore} / {maxTotalScore}</span>
                            </div>
                        )}
                    </div>
                )}
            </div>

            {}
            {!subtopicTitle ? (
                <div>
                    <p>Нажмите на тему слева, чтобы увидеть задания</p>
                </div>
            ) : tasks.length === 0 ? (
                <div>
                    <p>Для этой темы пока нет заданий</p>
                </div>
            ) : (
                <div>
                    {tasks.map((task) => (
                        <div
                            key={task.id}
                            onClick={() => onTaskClick?.(task)}
                        >
                            <div>
                                {}
                                <div>
                                    {task.status === 'completed' && '✓'}
                                    {task.status === 'in_progress' && '◯'}
                                    {task.status === 'locked'}
                                </div>

                                {}
                                <div>
                                    <div>
                                        <span>{task.title}</span>
                                        {task.type && <span>({task.type})</span>}
                                    </div>
                                    <div>{task.description}</div>

                                    {}
                                    {task.maxScore !== undefined && (
                                        <div>
                                            <span>Очки: {task.userScore || 0} / {task.maxScore}</span>
                                        </div>
                                    )}

                                    {}
                                    <div>
                                        {task.status === 'completed' && 'Пройдено'}
                                        {task.status === 'in_progress' && 'В процессе'}
                                        {task.status === 'locked' && 'Закрыто'}
                                    </div>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
