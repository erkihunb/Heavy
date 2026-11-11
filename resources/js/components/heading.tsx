export default function Heading({
    title,
    description,
    variant = 'default',
}: {
    title: string;
    description?: string;
    variant?: 'default' | 'small';
}) {
    return (
        <header className={variant === 'small' ? '' : 'mb-8 space-y-1.5'}>
            <h2
                className={
                    variant === 'small'
                        ? 'mb-0.5 text-lg font-bold'
                        : 'text-2xl text-display sm:text-3xl'
                }
            >
                {title}
            </h2>
            {description && (
                <p className="text-sm text-muted-foreground">{description}</p>
            )}
        </header>
    );
}
