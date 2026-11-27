<script setup lang="ts">
import { ref } from 'vue';
import KioskIdentify from '../../components/KioskIdentify.vue';
import PixPayment from '../../components/PixPayment.vue';
import KioskLayout from '../../layouts/KioskLayout.vue';
import { firstName } from '../../lib/format';
import { findStudent } from '../../lib/store';

const studentId = ref<string | null>(null);
</script>

<template>
    <KioskLayout
        :title="
            studentId
                ? `Mensalidade de ${firstName(findStudent(studentId)?.name ?? '')}`
                : 'Pagar mensalidade'
        "
        :idle-seconds="90"
    >
        <KioskIdentify
            v-if="studentId === null"
            @identified="(id) => (studentId = id)"
        />
        <div v-else class="mx-auto w-full max-w-xl">
            <PixPayment :student-id="studentId" size="kiosk" />
        </div>
    </KioskLayout>
</template>
