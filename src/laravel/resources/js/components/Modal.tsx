import {
    Dialog,
    DialogBackdrop,
    DialogPanel,
} from '@headlessui/react';
import { PropsWithChildren } from 'react';

export default function Modal({
    children,
    show = false,
    maxWidth = '2xl',
    closeable = true,
    onClose = () => {},
}: PropsWithChildren<{
    show: boolean;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl';
    closeable?: boolean;
    onClose: CallableFunction;
}>) {
    const close = () => {
        if (closeable) {
            onClose();
        }
    };

    const maxWidthClass = {
        sm: 'sm:max-w-sm',
        md: 'sm:max-w-md',
        lg: 'sm:max-w-lg',
        xl: 'sm:max-w-xl',
        '2xl': 'sm:max-w-2xl',
    }[maxWidth];

    return (
        <Dialog open={show} onClose={close} className="relative z-[60]">
            <DialogBackdrop
                transition
                className="fixed inset-0 bg-gray-500/75 transition duration-200 ease-out data-[closed]:opacity-0"
            />

            <div className="fixed inset-0 z-[60] flex items-center justify-center overflow-y-auto p-4 sm:p-6">
                <DialogPanel
                    transition
                    className={`w-full transform overflow-hidden rounded-lg bg-white shadow-xl transition duration-200 ease-out data-[closed]:scale-95 data-[closed]:opacity-0 dark:bg-gray-900 sm:mx-auto ${maxWidthClass}`}
                >
                    {children}
                </DialogPanel>
            </div>
        </Dialog>
    );
}
