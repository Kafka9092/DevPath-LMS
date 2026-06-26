import React, { useState } from 'react';
import axios from 'axios';

interface Task {
    id: number;
    title: string;
    code: string;
}

interface CodeReviewResponse {
    feedback: string;
    score?: number;
}

const tasks: Task[] = [
    { id: 1, title: 'SQL-инъекция', code: `$username = $_POST['username'];
$password = $_POST['password'];
$sql = "SELECT * FROM users WHERE username = '$username' AND password = '$password'";
$result = mysqli_query($conn, $sql);` },
    { id: 2, title: 'Гонка состояний', code: `let counter = 0;
async function increment() {
    counter++;
}
for (let i = 0; i < 1000; i++) {
    increment();
}
console.log(counter); // не всегда 1000` },
    { id: 3, title: 'Утечка памяти', code: `class Node {
    constructor(data) {
        this.data = data;
        this.next = null;
    }
}
let head = null;
for (let i = 0; i < 1000000; i++) {
    let node = new Node(i);
    node.next = head;
    head = node;
}` },
    { id: 4, title: 'Легаси PHP', code: `function getData() {
    $a = $_GET['id'];
    $sql = "SELECT * FROM users WHERE id = $a";
    $result = mysql_query($sql);
    return $result;
}` },
    { id: 5, title: 'Нечитаемый код', code: `function a($b, $c) {
    $d = 0;
    for($i=0;$i<count($b);$i++) {
        if($b[$i] > $c) {
            $d++;
        }
    }
    return $d;
}` },
];

export default function CodeReviewTasks() {
    const [selectedTask, setSelectedTask] = useState<Task | null>(null);
    const [comments, setComments] = useState<string>('');
    const [feedback, setFeedback] = useState<string>('');
    const [loading, setLoading] = useState<boolean>(false);

    const handleSubmit = async () => {
        if (!comments.trim()) {
            alert('Напишите ваши замечания');
            return;
        }
        if (!selectedTask) return;

        setLoading(true);
        try {
            const response = await axios.post<CodeReviewResponse>('/senior/codereview', {
                task_id: selectedTask.id,
                code: selectedTask.code,
                comments: comments
            });
            setFeedback(response.data.feedback);
        } catch (error) {
            console.error('Ошибка:', error);
            setFeedback('Ошибка при проверке. Попробуйте позже.');
        } finally {
            setLoading(false);
        }
    };

    if (!selectedTask) {
        return (
            <div>
                <h3>Code Review практика</h3>
                <div>
                    {tasks.map(t => (
                        <div key={t.id}>
                            <button onClick={() => setSelectedTask(t)}>{t.title}</button>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    return (
        <div>
            <button onClick={() => setSelectedTask(null)}>← Назад к списку</button>
            <h3>{selectedTask.title}</h3>
            <pre style={{ background: '#f4f4f4', padding: '12px', overflowX: 'auto' }}>{selectedTask.code}</pre>
            <textarea
                rows={8}
                cols={70}
                value={comments}
                onChange={(e) => setComments(e.target.value)}
                placeholder="Напишите, что не так с этим кодом и как исправить..."
            />
            <br />
            <button onClick={handleSubmit} disabled={loading}>
                {loading ? 'Проверка...' : 'Отправить'}
            </button>
            {feedback && <p>{feedback}</p>}
        </div>
    );
}
