import React, { useState } from 'react';
import axios from 'axios';

interface Case {
    id: number;
    title: string;
    description: string;
}

interface ArchitectureResponse {
    feedback: string;
    score?: number;
}

const cases: Case[] = [
    { id: 1, title: 'URL-шортнер', description: 'Спроектируйте систему сокращения ссылок (как bit.ly). Опишите: хранение, генерацию ключей, редирект, аналитику.' },
    { id: 2, title: 'Чат для 1 млн пользователей', description: 'Спроектируйте систему чата с WebSocket, балансировкой нагрузки, хранением истории.' },
    { id: 3, title: 'Instagram-like лайки', description: 'Спроектируйте систему лайков для 100 млн пользователей. Кеширование, шардирование, согласованность.' },
    { id: 4, title: 'Бронирование билетов', description: 'Спроектируйте систему бронирования билетов (как на поезд). Решите проблему двойного бронирования.' },
    { id: 5, title: 'Новостная лента', description: 'Спроектируйте новостную ленту для миллиона подписчиков (Twitter). Fan-out, кеширование, доставка.' },
    { id: 6, title: 'Оптимизация запроса', description: 'Дан медленный SQL запрос. Как найти узкое место? Какие инструменты? Как исправить?' },
    { id: 7, title: 'Выбор технологии', description: 'Сравните Go vs PHP vs Node.js для высоконагруженного проекта. Аргументируйте.' },
    { id: 8, title: 'Миграция в микросервисы', description: 'Дан монолит. Как определить границы сервисов? Риски? Отказоустойчивость?' },
    { id: 9, title: 'Отказоустойчивость', description: 'Спроектируйте систему с репликацией БД, резервным копированием, failover.' },
    { id: 10, title: 'Безопасность API', description: 'Как защитить API от DDoS, брутфорса, SQL-инъекций, XSS? Опишите меры.' },
];

export default function ArchitectureCases() {
    const [selectedCase, setSelectedCase] = useState<Case | null>(null);
    const [answer, setAnswer] = useState<string>('');
    const [feedback, setFeedback] = useState<string>('');
    const [loading, setLoading] = useState<boolean>(false);

    const handleSubmit = async () => {
        if (!answer.trim()) {
            alert('Напишите ваш ответ');
            return;
        }
        if (!selectedCase) return;

        setLoading(true);
        try {
            const response = await axios.post<ArchitectureResponse>('/senior/architecture', {
                case_id: selectedCase.id,
                answer: answer
            });
            setFeedback(response.data.feedback);
        } catch (error) {
            console.error('Ошибка:', error);
            setFeedback('Ошибка при проверке. Попробуйте позже.');
        } finally {
            setLoading(false);
        }
    };

    if (!selectedCase) {
        return (
            <div>
                <h3>Архитектурные кейсы</h3>
                <div>
                    {cases.map(c => (
                        <div key={c.id}>
                            <button onClick={() => setSelectedCase(c)}>{c.title}</button>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    return (
        <div>
            <button onClick={() => setSelectedCase(null)}>← Назад к списку</button>
            <h3>{selectedCase.title}</h3>
            <p>{selectedCase.description}</p>
            <textarea
                rows={10}
                cols={70}
                value={answer}
                onChange={(e) => setAnswer(e.target.value)}
                placeholder="Опишите ваше решение..."
            />
            <br />
            <button onClick={handleSubmit} disabled={loading}>
                {loading ? 'Проверка...' : 'Отправить'}
            </button>
            {feedback && <p>{feedback}</p>}
        </div>
    );
}
