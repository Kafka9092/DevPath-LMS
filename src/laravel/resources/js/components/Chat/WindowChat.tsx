import { ScrollArea as ScrollContainer } from "@/components/ui/scroll-area";
import { Avatar as UserAvatar, AvatarFallback as AvatarInitials } from "@/components/ui/avatar";


const CHAT_MODES = {
    TUTORING: 'learning',
    INTERVIEWING: 'interview',
    REVIEWING: 'codeReview'
} as const;

type ValidChatMode = typeof CHAT_MODES[keyof typeof CHAT_MODES];

interface ChatWindowProperties {
    mode: ValidChatMode;
}

const GRADIENT_SCHEMES: Record<ValidChatMode, string> = {
    [CHAT_MODES.TUTORING]: 'from-pink-400 to-fuchsia-500',
    [CHAT_MODES.INTERVIEWING]: 'from-slate-400 to-gray-600',
    [CHAT_MODES.REVIEWING]: 'from-orange-400 to-red-500',
};

export default function ChatInterface({ mode }: ChatWindowProperties) {
    const currentGradient = GRADIENT_SCHEMES[mode];

    const renderActiveConversation = () => {
        switch(mode) {
            case CHAT_MODES.TUTORING:
                return <TutorConversation />;
            case CHAT_MODES.INTERVIEWING:
                return <JobSimulation />;
            case CHAT_MODES.REVIEWING:
                return <CodeAnalysis />;
            default:
                return null;
        }
    };

    return (
        <section className={`h-full rounded-2xl shadow-lg bg-gradient-to-br ${currentGradient} p-6 flex flex-col relative overflow-hidden`}>

            <ScrollContainer className="flex-1 z-10 pr-4 [&>div]:!block">
                <div className="space-y-4">
                    {renderActiveConversation()}
                </div>
            </ScrollContainer>
        </section>
    );
}
