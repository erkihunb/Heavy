import { BrandMark } from '@/components/brand-mark';

type Props = {
    className?: string;
};

/**
 * The system's logo in the shell (ApplicationLogo): the logo an Admin
 * uploaded in System settings → General, otherwise Heavy's placeholder mark.
 * A logo that fails to load falls back to the mark, never a broken image.
 */
export default function ApplicationLogo({ className = 'h-8 w-8' }: Props) {
    return <BrandMark className={className} />;
}
