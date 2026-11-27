import { reserveClass } from './store';
import { showToast } from './toast';

/** Reserva + feedback padronizado (app e totem). */
export function reserveWithFeedback(classId: string, studentId?: string): void {
    const result = reserveClass(classId, studentId);

    if (result === null) {
        return;
    }

    if (result.status === 'confirmada') {
        showToast({
            title: 'Vaga confirmada',
            description: 'Lembraremos você 1 hora antes.',
            tone: 'ok',
        });
    } else if (result.status === 'fila') {
        showToast({
            title: `Você é o ${result.position}º da fila`,
            description: 'Se alguém cancelar, a vaga é sua e avisamos na hora.',
            tone: 'info',
        });
    } else {
        showToast({ title: 'Você já está nesta aula', tone: 'info' });
    }
}
