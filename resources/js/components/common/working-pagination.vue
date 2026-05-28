<template>
    <nav>
        <ul class="pagination justify-content-center">
            <li class="page-item" :class="{ disabled: currentPage === 1 }">
                <BButton class="page-link" @click="changePage(currentPage - 1)">
                    &laquo;
                </BButton>
            </li>
            <li v-for="page in totalPages" :key="page" class="page-item" :class="{ active: currentPage === page }">
                <BButton class="page-link" @click="changePage(page)">
                    {{ page }}
                </BButton>
            </li>
            <li class="page-item" :class="{ disabled: currentPage === totalPages }">
                <BButton class="page-link" @click="changePage(currentPage + 1)">
                    &raquo;
                </BButton>
            </li>
        </ul>
    </nav>
</template>

<script>
export default {
    props: {
        totalPages: Number,
        currentPage: Number,
    },
    methods: {
        changePage(page) {
            if (page > 0 && page <= this.totalPages) {
                this.$emit("page-changed", page);
            }
        },
    },
};
</script>