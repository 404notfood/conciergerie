import type { SVGAttributes } from 'react';
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 40 40"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path d="M4 17 20 4l16 13v19H25V22H15v14H4V17Z" fill="#3155b9" />
            <path d="m20 4 16 13v19h-7V20L20 12V4Z" fill="#6486e1" />
            <path d="M9 20v16h6V22l5-4V12L9 20Z" fill="#dce5fc" />
        </svg>
    );
}
