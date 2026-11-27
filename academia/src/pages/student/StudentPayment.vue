<script setup lang="ts">
import PixPayment from '../../components/PixPayment.vue';
import StudentLayout from '../../layouts/StudentLayout.vue';
import { currentStudent } from '../../lib/store';
</script>

<template>
    <StudentLayout title="Pagar mensalidade" back="/aluno">
        <PixPayment :student-id="currentStudent.id" />
    </StudentLayout>
</template>
