// Icons from TempoVibe (src/components/Icons.tsx), only those in use so far
import type { ReactNode } from 'react';

interface IconProps { size?: number }

function Svg({ size = 22, strokeWidth = 2, children }: { size?: number; strokeWidth?: number; children: ReactNode }) {
  return (
    <svg width={size} height={size} viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={strokeWidth} strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
      {children}
    </svg>
  );
}

export const IconGlobe = (p: IconProps) => <Svg {...p}><circle cx="12" cy="12" r="9" /><path d="M3 12h18M12 3c3 3.5 3 14.5 0 18M12 3c-3 3.5-3 14.5 0 18" /></Svg>;
