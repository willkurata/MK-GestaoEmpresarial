import type { BookingResult, GymClass } from './types';

export function spotsLeft(gymClass: GymClass): number {
    return Math.max(0, gymClass.capacity - gymClass.bookedStudentIds.length);
}

/**
 * Reserva uma vaga. Sem vaga, o aluno entra na fila de espera e é promovido
 * automaticamente quando alguém cancela.
 */
export function bookClass(
    gymClass: GymClass,
    studentId: string,
): BookingResult {
    if (
        gymClass.bookedStudentIds.includes(studentId) ||
        gymClass.waitlistStudentIds.includes(studentId)
    ) {
        return { status: 'ja_inscrito' };
    }

    if (spotsLeft(gymClass) > 0) {
        gymClass.bookedStudentIds.push(studentId);

        return { status: 'confirmada' };
    }

    gymClass.waitlistStudentIds.push(studentId);

    return { status: 'fila', position: gymClass.waitlistStudentIds.length };
}

/**
 * Cancela a reserva ou a posição na fila. Retorna o id do aluno promovido
 * da fila (para disparar a notificação "vaga liberada"), se houver.
 */
export function cancelBooking(
    gymClass: GymClass,
    studentId: string,
): string | null {
    const waitlistIndex = gymClass.waitlistStudentIds.indexOf(studentId);

    if (waitlistIndex >= 0) {
        gymClass.waitlistStudentIds.splice(waitlistIndex, 1);

        return null;
    }

    const bookedIndex = gymClass.bookedStudentIds.indexOf(studentId);

    if (bookedIndex < 0) {
        return null;
    }

    gymClass.bookedStudentIds.splice(bookedIndex, 1);
    const promoted = gymClass.waitlistStudentIds.shift() ?? null;

    if (promoted !== null) {
        gymClass.bookedStudentIds.push(promoted);
    }

    return promoted;
}

export function bookingStateFor(
    gymClass: GymClass,
    studentId: string,
): 'confirmada' | 'fila' | 'livre' {
    if (gymClass.bookedStudentIds.includes(studentId)) {
        return 'confirmada';
    }

    if (gymClass.waitlistStudentIds.includes(studentId)) {
        return 'fila';
    }

    return 'livre';
}
