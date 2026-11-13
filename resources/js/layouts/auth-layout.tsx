import AuthLayoutTemplate from '@/layouts/auth/auth-split-layout';

export default function AuthLayout({
    children,
    title,
    description,
    ...props
}: {
    children: React.ReactNode;
    title: string;
    description: string;
    /** Draw the page with no entrance at all; the sign-in page passes this. */
    still?: boolean;
    /** An illustration for the brand panel; the sign-in page passes one. */
    scene?: React.ReactNode;
}) {
    return (
        <AuthLayoutTemplate title={title} description={description} {...props}>
            {children}
        </AuthLayoutTemplate>
    );
}
