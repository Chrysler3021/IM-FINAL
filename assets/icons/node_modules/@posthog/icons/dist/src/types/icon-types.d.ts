import { ComponentProps, FunctionComponent, RefAttributes } from 'react';

export type BaseIconProps = ComponentProps<'svg'>;
export type IconProps = Omit<BaseIconProps, 'children'>;
export type IconComponent<T> = FunctionComponent<T & RefAttributes<SVGSVGElement>>;
