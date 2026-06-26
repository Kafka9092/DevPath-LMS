import { useState } from 'react';

export interface Subtopic {
    id: string;
    title: string;
    content?: string;      
    description: string;  
    task: string;         
    codeExample?: string;
}

export interface Topic {
    id: string;
    title: string;
    subtopics: Subtopic[];
}

interface LearningSidebarProps {
    courseTitle: string;
    topics: Topic[];
    currentSubtopicId?: string;
    completedIds?: string[];
    onSelectSubtopic: (subtopic: Subtopic) => void;
}

export default function LearningSidebar({
    courseTitle,
    topics,
    currentSubtopicId,
    completedIds = [],
    onSelectSubtopic
}: LearningSidebarProps) {
    const [expandedTopics, setExpandedTopics] = useState<string[]>([]);

    const toggleTopic = (topicId: string) => {
        setExpandedTopics((prev) =>
            prev.includes(topicId)
                ? prev.filter((id) => id !== topicId)
                : [...prev, topicId]
        );
    };

    return (
        <div>
            {}
            <div>
                <h2>{courseTitle}</h2>
            </div>

            {}
            <div>
                {topics.map((topic) => (
                    <div key={topic.id}>
                        {}
                        <div onClick={() => toggleTopic(topic.id)}>
                            <span>{topic.title}</span>
                            <span>{expandedTopics.includes(topic.id) ? '▼' : '►'}</span>
                        </div>

                        {}
                        {expandedTopics.includes(topic.id) && (
                            <div>
                                {topic.subtopics.map((subtopic) => (
                                    <div
                                        key={subtopic.id}
                                        onClick={() => onSelectSubtopic(subtopic)}
                                        style={{
                                            fontWeight: currentSubtopicId === subtopic.id ? 'bold' : 'normal',
                                            color: completedIds.includes(subtopic.id) ? 'green' : 'black'
                                        }}
                                    >
                                        {completedIds.includes(subtopic.id) && '✅ '}
                                        {subtopic.title}
                                    </div>
                                ))}
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}
