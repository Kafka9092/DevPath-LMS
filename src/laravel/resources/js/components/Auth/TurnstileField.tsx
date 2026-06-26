import { authErrorClass } from '@/components/Auth/authStyles';
import InputError from '@/components/InputError';
import { Turnstile } from '@marsidev/react-turnstile';

type TurnstileFieldProps = {
    siteKey: string;
    onSuccess: (token: string) => void;
    onExpire: () => void;
    error?: string;
};

export default function TurnstileField({
    siteKey,
    onSuccess,
    onExpire,
    error,
}: TurnstileFieldProps) {
    if (!siteKey) {
        return null;
    }

    return (
        <div>
            <Turnstile
                siteKey={siteKey}
                onSuccess={onSuccess}
                onExpire={onExpire}
                options={{ theme: 'auto' }}
            />
            <InputError message={error} className={authErrorClass} />
        </div>
    );
}
