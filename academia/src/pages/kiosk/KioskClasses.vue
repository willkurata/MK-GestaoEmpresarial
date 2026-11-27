<script setup lang="ts">
import { computed, ref } from 'vue';
import ClassCard from '../../components/ClassCard.vue';
import KioskIdentify from '../../components/KioskIdentify.vue';
import KioskLayout from '../../layouts/KioskLayout.vue';
import { reserveWithFeedback } from '../../lib/booking';
import { firstName, toLocalDateString } from '../../lib/format';
import { cancelClass, findStudent, state } from '../../lib/store';

const studentId = ref<string | null>(null);
const today = toLocalDateString(new Date());

const classes = computed(() =>
    state.classes
        .filter(
            (gymClass) =>
                toLocalDateString(new Date(gymClass.startsAt)) === today,
        )
        .sort((a, b) => a.startsAt.localeCompare(b.startsAt)),
);
</script>

<template>
    <KioskLayout
        :title="
            studentId
                ? `Aulas de hoje, ${firstName(findStudent(studentId)?.name ?? '')}`
                : 'Reservar aula'
        "
        :idle-seconds="45"
    >
        <KioskIdentify
            v-if="studentId === null"
            @identified="(id) => (studentId = id)"
        />
        <div v-else class="mx-auto w-full max-w-3xl space-y-4">
            <ClassCard
                v-for="gymClass in classes"
                :key="gymClass.id"
                :gym-class="gymClass"
                :student-id="studentId"
                size="kiosk"
                @book="reserveWithFeedback(gymClass.id, studentId)"
                @cancel="cancelClass(gymClass.id, studentId)"
            />
        </div>
    </KioskLayout>
</template>
