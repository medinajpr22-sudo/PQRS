import { ChevronDownIcon } from "lucide-react"
import * as React from "react"

import { cn } from "@/lib/utils"

// Clases de tamaño/posición (w-44, lg:w-60, flex-1…): van al contenedor,
// para que el select y su flecha ocupen ese ancho y no toda la fila.
const LAYOUT_CLASS = /^([a-z0-9-]+:)*(w-|min-w-|max-w-|flex-|basis-|grow|shrink|self-)/

/**
 * Select nativo con el estilo del sistema (funciona dentro de <Form> sin estado).
 */
function NativeSelect({ className, children, ...props }: React.ComponentProps<"select">) {
  const tokens = (className ?? "").split(/\s+/).filter(Boolean)
  const layout = tokens.filter((t) => LAYOUT_CLASS.test(t))
  const look = tokens.filter((t) => !LAYOUT_CLASS.test(t))
  const hasWidth = layout.some((t) => /(^|:)(w-|flex-1)/.test(t))

  return (
    <div className={cn("relative", !hasWidth && "w-full", layout.join(" "))}>
      <select
        data-slot="native-select"
        className={cn(
          "border-input focus-visible:border-ring focus-visible:ring-ring/50 aria-invalid:ring-destructive/20 aria-invalid:border-destructive dark:bg-input/30 h-9 w-full appearance-none rounded-md border bg-transparent py-1 pr-9 pl-3 text-base shadow-xs outline-none transition-[color,box-shadow] focus-visible:ring-[3px] disabled:cursor-not-allowed disabled:opacity-50 md:text-sm",
          look.join(" ")
        )}
        {...props}
      >
        {children}
      </select>
      <ChevronDownIcon className="text-muted-foreground pointer-events-none absolute top-1/2 right-3 size-4 -translate-y-1/2" />
    </div>
  )
}

export { NativeSelect }
