import { formatLetterDate } from '@/lib/format';

/**
 * Plantillas de «Ayuda a redactar»: puntos de partida para responder una
 * PQRS. Se llenan solas con los datos de la solicitud (nombre, radicado,
 * fecha, asunto…) y dejan entre [corchetes] lo que el funcionario debe
 * escribir. No es una IA: son textos fijos pensados para PQRS de salud.
 */

export type TemplateId = 'formal' | 'apology' | 'thanks';

export type TemplateContext = {
    requesterName: string;
    radicado: string;
    /** Fecha de radicación (ISO). */
    filedAt: string;
    /** Clave del tipo (peticion, queja, reclamo…). */
    type: string;
    typeLabel: string;
    subject: string;
    companyName: string;
    agentName: string;
};

export type ResponseTemplate = {
    id: TemplateId;
    title: string;
    description: string;
    body: string;
    /** Los textos entre corchetes que faltan por completar. */
    placeholders: string[];
};

/** Partículas que van en minúscula al arreglar un nombre escrito en mayúsculas. */
const NAME_PARTICLES = new Set(['de', 'del', 'la', 'las', 'los', 'y', 'e']);

/**
 * «MARÍA DE LOS ÁNGELES PÉREZ» → «María de los Ángeles Pérez». Si el
 * ciudadano escribió su nombre normal, se deja como está.
 */
export function displayName(name: string): string {
    const trimmed = name.trim().replace(/\s+/g, ' ');

    if (trimmed !== trimmed.toLocaleUpperCase('es')) {
        return trimmed;
    }

    return trimmed
        .toLocaleLowerCase('es')
        .split(' ')
        .map((word, i) =>
            i > 0 && NAME_PARTICLES.has(word)
                ? word
                : word.charAt(0).toLocaleUpperCase('es') + word.slice(1),
        )
        .join(' ');
}

/**
 * La plantilla que mejor encaja con el tipo de solicitud.
 */
export function suggestedTemplate(type: string): TemplateId {
    if (type === 'queja' || type === 'reclamo') {
        return 'apology';
    }

    if (type === 'felicitacion' || type === 'sugerencia') {
        return 'thanks';
    }

    return 'formal';
}

function thanksMiddle(type: string): string {
    if (type === 'felicitacion') {
        return 'Compartiremos su mensaje con [nombre de la persona o del área que felicita] como reconocimiento a su labor y a su compromiso con nuestros usuarios.';
    }

    if (type === 'sugerencia') {
        return 'Su propuesta fue remitida a [área responsable], que evaluará la posibilidad de implementarla para mejorar nuestros servicios.';
    }

    return '[Escriba aquí la respuesta.]';
}

export function responseTemplates(ctx: TemplateContext): ResponseTemplate[] {
    const name = displayName(ctx.requesterName);
    const type = ctx.typeLabel.toLocaleLowerCase('es');
    const date = formatLetterDate(ctx.filedAt);
    const reference = `su ${type} con radicado No. ${ctx.radicado} del ${date}`;
    const signature = `Cordialmente,\n\n${ctx.agentName}\n${ctx.companyName}`;

    const templates: Omit<ResponseTemplate, 'placeholders'>[] = [
        {
            id: 'formal',
            title: 'Respuesta formal',
            description: 'Sirve para cualquier tipo de solicitud.',
            body: [
                `Cordial saludo, ${name}:`,
                `En atención a ${reference}, sobre «${ctx.subject}», nos permitimos dar respuesta en los siguientes términos:`,
                '[Escriba aquí la respuesta de fondo: qué se revisó, qué se encontró y qué se decidió.]',
                '[Si aplica, indique los pasos a seguir: fecha de la cita, documentos o trámite que debe realizar el usuario. Si no aplica, borre este párrafo.]',
                `Si tiene alguna inquietud adicional, puede comunicarse con nosotros citando el radicado ${ctx.radicado}.`,
                signature,
            ].join('\n\n'),
        },
        {
            id: 'apology',
            title: 'Disculpa y acciones de mejora',
            description: 'Para quejas y reclamos.',
            body: [
                `Cordial saludo, ${name}:`,
                `Hemos recibido ${reference}, sobre «${ctx.subject}». Lamentamos sinceramente los inconvenientes que esta situación le ha ocasionado.`,
                'Una vez revisado su caso, le informamos que [describa lo que se encontró al revisar el caso].',
                'Para evitar que esta situación se repita, tomamos las siguientes acciones de mejora:\n- [Acción de mejora 1]\n- [Acción de mejora 2]',
                'Sus comentarios nos ayudan a mejorar la calidad de nuestros servicios. Quedamos atentos a cualquier inquietud adicional.',
                signature,
            ].join('\n\n'),
        },
        {
            id: 'thanks',
            title: 'Agradecimiento',
            description: 'Para felicitaciones y sugerencias.',
            body: [
                `Cordial saludo, ${name}:`,
                `Recibimos con mucho gusto ${reference}. Le agradecemos que se haya tomado el tiempo de escribirnos.`,
                thanksMiddle(ctx.type),
                'Comentarios como el suyo nos motivan a seguir mejorando cada día.',
                signature,
            ].join('\n\n'),
        },
    ];

    const suggested = suggestedTemplate(ctx.type);

    // Un asunto o nombre con corchetes no cuenta como texto por completar.
    const fromCitizen = `${name} ${ctx.subject}`;

    return templates
        .map((t) => ({
            ...t,
            placeholders: findPlaceholders(t.body).filter(
                (p) => !fromCitizen.includes(p),
            ),
        }))
        .sort(
            (a, b) => Number(b.id === suggested) - Number(a.id === suggested),
        );
}

/**
 * Los textos entre [corchetes] de una plantilla, sin repetir.
 */
export function findPlaceholders(text: string): string[] {
    return [...new Set(text.match(/\[[^\]\n]+\]/g) ?? [])];
}
