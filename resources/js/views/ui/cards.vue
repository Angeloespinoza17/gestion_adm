<script>
import Layout from "../../layouts/main.vue";
import PageHeader from "../../components/page-header.vue";

import img1 from '../../../images/small/img-1.jpg';
import img2 from '../../../images/small/img-2.jpg';
import img3 from '../../../images/small/img-3.jpg';
import img4 from '../../../images/small/img-4.jpg';
import img5 from '../../../images/small/img-5.jpg';
import img6 from '../../../images/small/img-6.jpg';
import img7 from '../../../images/small/img-7.jpg';
/**
 * Cards component
 */
export default {
    components: {
        Layout,
        PageHeader
    },
    data() {
        return {
            img1, img2, img3, img4, img5, img6, img7,
        };
    }
};
</script>

<template>
    <Layout>
        <PageHeader title="Cards" pageTitle="UI Elements" />

        <BRow>
            <BCol lg="6" xl="3">
                <!-- Simple card -->
                <BCard :img-src="img1" img-alt="Card image" img-top>
                    <BCardTitle class="h5">Card title</BCardTitle>
                    <BCardText>
                        Some quick example text to build on the card title and make
                        up the bulk of the card's content.
                    </BCardText>
                    <BButton href="javascript:void(0);" variant="primary">Button</BButton>
                </BCard>
            </BCol>

            <BCol lg="6" xl="3">
                <BCard no-body :img-src="img2" img-alt="Card image" img-top>
                    <BCardBody>
                        <BCardTitle class="h5">Card title</BCardTitle>
                        <BCardText>Some quick example text to build on the card title and make up the bulk of the card's
                            content.</BCardText>
                    </BCardBody>
                    <BListGroup>
                        <BListGroupItem>Cras justo odio</BListGroupItem>
                        <BListGroupItem>Dapibus ac facilisis in</BListGroupItem>
                    </BListGroup>
                    <BCardBody>
                        <BLink href="javascript:void(0);" class="card-link text-custom">Card link</BLink>
                        <BLink href="javascript:void(0);" class="card-link text-custom">Another link</BLink>
                    </BCardBody>
                </BCard>
            </BCol>

            <BCol lg="6" xl="3">
                <BCard :img-src="img3" img-alt="Card image" img-top>
                    <BCardText>
                        Some quick example text to build on the card title and make
                        up the bulk of the card's content.
                    </BCardText>
                </BCard>
            </BCol>

            <BCol lg="6" xl="3">
                <BCard no-body>
                    <BCardBody>
                        <BCardTitle>Card title</BCardTitle>
                        <BCardSubtitle>
                            <h6 class="text-muted">Support card subtitle</h6>
                        </BCardSubtitle>
                    </BCardBody>
                    <img :src="img4" class="img-fluid" />
                    <BCardBody>
                        <BCardText>
                            Some quick example text to build on the card title and make
                            up the bulk of the card's content.
                        </BCardText>
                        <BLink href="javascript:void(0);" class="card-link text-custom">Card link</BLink>
                        <BLink href="javascript:void(0);" class="card-link text-custom">Another link</BLink>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>

        <BRow>
            <BCol lg="6">
                <BCard no-body>
                    <BCardBody>
                        <BCardTitle>Special title treatment</BCardTitle>
                        <BCardText>
                            With supporting text below as a natural lead-in to additional
                            content.
                        </BCardText>
                        <BLink href="javascript:void(0);" class="btn btn-primary btn-block waves-effect waves-light">Go
                            somewhere</BLink>
                    </BCardBody>
                </BCard>
            </BCol>
            <BCol lg="6">
                <BCard no-body>
                    <BCardBody>
                        <BCardTitle>Special title treatment</BCardTitle>
                        <BCardText>
                            With supporting text below as a natural lead-in to additional
                            content.
                        </BCardText>
                        <BLink href="javascript:void(0);" class="btn btn-primary btn-block waves-effect waves-light">Go
                            somewhere</BLink>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>

        <BRow>
            <BCol md="4">
                <BCard no-body>
                    <BCardBody>
                        <BCardTitle>Special title treatment</BCardTitle>
                        <BCardText>
                            With supporting text below as a natural lead-in to additional
                            content.
                        </BCardText>
                        <BLink href="javascript:void(0);" class="btn btn-primary btn-block waves-effect waves-light">Go
                            somewhere</BLink>
                    </BCardBody>
                </BCard>
            </BCol>
            <BCol md="4">
                <BCard no-body class="text-center">
                    <BCardBody>
                        <BCardTitle>Special title treatment</BCardTitle>
                        <BCardText>
                            With supporting text below as a natural lead-in to additional
                            content.
                        </BCardText>
                        <BLink href="javascript:void(0);" class="btn btn-primary btn-block waves-effect waves-light">Go
                            somewhere</BLink>
                    </BCardBody>
                </BCard>
            </BCol>
            <BCol md="4">
                <BCard no-body class="text-end">
                    <BCardBody>
                        <BCardTitle>Special title treatment</BCardTitle>
                        <BCardText>
                            With supporting text below as a natural lead-in to additional
                            content.
                        </BCardText>
                        <BLink href="javascript:void(0);" class="btn btn-primary btn-block waves-effect waves-light">Go
                            somewhere</BLink>
                    </BCardBody>
                </BCard>
            </BCol>
        </BRow>

        <BRow>
            <BCol md="4">
                <BCard>
                    <BCardTitle class="h5">Special title treatment</BCardTitle>
                    <h5 class="mb-0">Featured</h5>
                    <BCardText>With supporting text below as a natural lead-in to additional
                        content.</BCardText>
                    <BButton href="javascript: void(0);" variant="primary">Go somewhere</BButton>
                </BCard>
            </BCol>
            <BCol md="4">
                <BCard header="Quote">
                    <BCardText>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer
                        posuere erat a ante.</BCardText>
                    <BCardFooter>
                        Someone famous in
                        <cite title="Source Title">Source Title</cite>
                    </BCardFooter>
                </BCard>
            </BCol>
            <BCol md="4">
                <BCard header="Featured" footer="2 days ago" class="text-center">
                    <BCardText>
                        <BCardTitle>Special title treatment</BCardTitle>
                        <BCardText>
                            With supporting text below as a natural lead-in to additional
                            content.
                        </BCardText>
                        <BLink href="javascript: void(0);" class="btn btn-primary">Go somewhere</BLink>
                    </BCardText>
                </BCard>
            </BCol>
        </BRow>

        <BRow>
            <BCol md="4">
                <BCard :img-src="img5" img-alt="Card image" img-top>
                    <BCardTitle>Card title</BCardTitle>
                    <BCardText>
                        <p>
                            This is a wider card with supporting text below as a natural
                            lead-in to additional content. This content is a little bit
                            longer.
                        </p>
                        <p>
                            <small class="text-muted">Last updated 3 mins ago</small>
                        </p>
                    </BCardText>
                </BCard>
            </BCol>
            <BCol md="4">
                <BCard :img-src="img7" img-alt="Card image" img-bottom>
                    <BCardTitle>Card title</BCardTitle>
                    <BCardText>
                        <p>
                            This is a wider card with supporting text below as a natural
                            lead-in to additional content. This content is a little bit
                            longer.
                        </p>
                        <p>
                            <small class="text-muted">Last updated 3 mins ago</small>
                        </p>
                    </BCardText>
                </BCard>
            </BCol>
            <BCol md="4">
                <BCard overlay :img-src="img6" img-alt="Card Image" text-variant="white">
                    <BCardTitle>
                        <h5 class="text-white">Card title</h5>
                    </BCardTitle>
                    <BCardText>
                        <p>
                            This is a wider card with supporting text below as a natural
                            lead-in to additional content. This content is a little bit
                            longer.
                        </p>
                        <p>
                            <small class="text-white">Last updated 3 mins ago</small>
                        </p>
                    </BCardText>
                </BCard>
            </BCol>
        </BRow>

        <BRow>
            <BCol lg="6">
                <BCard no-body>
                    <BRow no-gutters class="align-items-center">
                        <BCol md="4">
                            <BCardImg :src="img2" class="rounded-0"></BCardImg>
                        </BCol>
                        <BCol md="8">
                            <BCardBody title="Card title">
                                <BCardText>This is a wider card with supporting text below as a natural
                                    lead-in to additional content.</BCardText>
                                <BCardText>
                                    <small class="text-muted">Last updated 3 mins ago</small>
                                </BCardText>
                            </BCardBody>
                        </BCol>
                    </BRow>
                </BCard>
            </BCol>
            <BCol lg="6">
                <BCard no-body>
                    <BRow no-gutters class="align-items-center">
                        <BCol md="8">
                            <BCardBody title="Card title">
                                <BCardText>This is a wider card with supporting text below as a natural
                                    lead-in to additional content.</BCardText>
                                <BCardText>
                                    <small class="text-muted">Last updated 3 mins ago</small>
                                </BCardText>
                            </BCardBody>
                        </BCol>
                        <BCol md="4">
                            <BCardImg :src="img3" class="rounded-0"></BCardImg>
                        </BCol>
                    </BRow>
                </BCard>
            </BCol>
        </BRow>

        <BRow>
            <BCol md="4">
                <BCard bg-variant="primary" class="text-white-50">
                    <h5 class="mt-0 mb-4 text-white">
                        <i class="mdi mdi-bullseye-arrow me-3"></i> Primary Card
                    </h5>
                    <BCardText>
                        Some quick example text to build on the card title and make up the
                        bulk of the card's content.
                    </BCardText>
                </BCard>
            </BCol>
            <BCol md="4">
                <BCard bg-variant="success" class="text-white-50">
                    <h5 class="mt-0 mb-4 text-white">
                        <i class="mdi mdi-check-all me-3"></i> Success Card
                    </h5>
                    <BCardText>
                        Some quick example text to build on the card title and make up the
                        bulk of the card's content.
                    </BCardText>
                </BCard>
            </BCol>
            <BCol md="4">
                <BCard bg-variant="info" class="text-white-50">
                    <h5 class="mt-0 mb-4 text-white">
                        <i class="mdi mdi-alert-circle-outline me-3"></i> Info Card
                    </h5>
                    <BCardText>
                        Some quick example text to build on the card title and make up the
                        bulk of the card's content.
                    </BCardText>
                </BCard>
            </BCol>
        </BRow>

        <BRow>
            <BCol md="4">
                <BCard bg-variant="warning" class="text-white-50">
                    <h5 class="mt-0 mb-4 text-white">
                        <i class="mdi mdi-alert-outline me-3"></i> Warning Card
                    </h5>
                    <BCardText>
                        Some quick example text to build on the card title and make up the
                        bulk of the card's content.
                    </BCardText>
                </BCard>
            </BCol>
            <BCol md="4">
                <BCard bg-variant="danger" class="text-white-50">
                    <h5 class="mt-0 mb-4 text-white">
                        <i class="mdi mdi-block-helper me-3"></i> Danger Card
                    </h5>
                    <BCardText>
                        Some quick example text to build on the card title and make up the
                        bulk of the card's content.
                    </BCardText>
                </BCard>
            </BCol>
            <BCol md="4">
                <BCard bg-variant="dark" class="text-white-50">
                    <h5 class="mt-0 mb-4 text-white">
                        <i class="mdi mdi-alert-circle-outline me-3"></i> Dark Card
                    </h5>
                    <BCardText>
                        Some quick example text to build on the card title and make up the
                        bulk of the card's content.
                    </BCardText>
                </BCard>
            </BCol>
        </BRow>

        <BRow>
            <BCol lg="4">
                <BCard header-class="bg-transparent border-primary" class="border border-primary">
                    <template v-slot:header>
                        <h5 class="my-0 text-primary">
                            <i class="mdi mdi-bullseye-arrow me-3"></i>Primary outline Card
                        </h5>
                    </template>
                    <BCardTitle class="mt-0">card title</BCardTitle>
                    <BCardText>
                        Some quick example text to build on the card title and make up the
                        bulk of the card's content.
                    </BCardText>
                </BCard>
            </BCol>
            <BCol lg="4">
                <BCard header-class="bg-transparent border-danger" class="border border-danger">
                    <template v-slot:header>
                        <h5 class="my-0 text-danger">
                            <i class="mdi mdi-block-helper me-3"></i>Danger outline Card
                        </h5>
                    </template>
                    <BCardTitle class="mt-0">card title</BCardTitle>
                    <BCardText>
                        Some quick example text to build on the card title and make up the
                        bulk of the card's content.
                    </BCardText>
                </BCard>
            </BCol>
            <BCol lg="4">
                <BCard header-class="bg-transparent border-success" class="border border-success">
                    <template v-slot:header>
                        <h5 class="my-0 text-success">
                            <i class="mdi mdi-check-all me-3"></i>Success outline Card
                        </h5>
                    </template>
                    <BCardTitle class="mt-0">card title</BCardTitle>
                    <BCardText>
                        Some quick example text to build on the card title and make up the
                        bulk of the card's content.
                    </BCardText>
                </BCard>
            </BCol>
        </BRow>

        <BRow>
            <BCol sm="12">
                <h4 class="my-3">Cards Masonry</h4>

                <BRow data-masonry='{"percentPosition": true }'>
                    <BCol sm="6" lg="4">
                        <BCard no-body :img-src="img3" img-alt="Card image" img-top>
                            <BCardBody title="Card title that wraps to a new line">
                                <BCardText>This is a longer card with supporting text below as a natural lead-in
                                    to additional content. This content is a little bit longer.</BCardText>
                            </BCardBody>
                        </BCard>
                    </BCol>

                    <BCol sm="6" lg="4">
                        <BCard no-body :img-src="img5" img-alt="Card image" img-top>
                            <BCardBody title="Card title">
                                <BCardText>This card has supporting text below as a natural lead-in to additional
                                    content.</BCardText>
                                <p class="card-text"><small class="text-muted">Last updated 3 mins ago</small></p>
                            </BCardBody>
                        </BCard>
                    </BCol>

                    <BCol sm="6" lg="4">
                        <BCard no-body :img-src="img7" img-alt="Card image" img-top>
                        </BCard>
                    </BCol>

                    <BCol sm="6" lg="4">
                        <BCard>
                            <blockquote class="blockquote mb-0">
                                <p class="font-size-14">Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer
                                    posuere erat a ante.
                                </p>
                                <BCardFooter class="blockquote-footer mt-0">
                                    <small class="text-muted">
                                        Someone famous in <cite title="Source Title">Source Title</cite>
                                    </small>
                                </BCardFooter>
                            </blockquote>
                        </BCard>
                    </BCol>

                    <BCol sm="6" lg="4">
                        <BCard class="bg-primary text-white text-center p-3">
                            <blockquote class="card-blockquote font-size-14 mb-0">
                                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer posuere erat.</p>
                                <BCardFooter class="blockquote-footer mt-0 text-white font-size-12 mb-0">
                                    Someone famous in <cite title="Source Title">Source Title</cite>
                                </BCardFooter>
                            </blockquote>
                        </BCard>
                    </BCol>
                    <BCol sm="6" lg="4">
                        <BCard title="Card title">
                            <BCardText>This is another card with title and supporting text below. This card
                                has some additional content to make it slightly taller overall.</BCardText>
                            <BCardText><small class="text-muted">Last updated 3 mins ago</small></BCardText>
                        </BCard>
                    </BCol>
                    <BCol sm="6" lg="4">
                        <BCard title="Card title">
                            <BCardText>This is another card with title and supporting text below. This card
                                has some additional content to make it slightly taller overall.</BCardText>
                            <BCardText><small class="text-muted">Last updated 3 mins ago</small></BCardText>
                        </BCard>
                    </BCol>
                </BRow>
            </BCol>
        </BRow>

        <div class="row">
            <div class="col-12">
                <h4 class="my-3">Card groups</h4>
                <div class="card-deck-wrapper">
                    <div class="card-group">
                        <div class="card mb-4">
                            <img class="card-img-top img-fluid" src="@/assets/images/small/img-4.jpg"
                                alt="Card image cap">
                            <div class="card-body">
                                <h4 class="card-title">Card title</h4>
                                <p class="card-text">This is a longer card with supporting text below as
                                    a natural lead-in to additional content. This content is a little
                                    bit longer.</p>
                                <p class="card-text">
                                    <small class="text-muted">Last updated 3 mins ago</small>
                                </p>
                            </div>
                        </div>
                        <div class="card mb-4">
                            <img class="card-img-top img-fluid" src="@/assets/images/small/img-5.jpg"
                                alt="Card image cap">
                            <div class="card-body">
                                <h4 class="card-title">Card title</h4>
                                <p class="card-text">This card has supporting text below as a natural
                                    lead-in to additional content.</p>
                                <p class="card-text">
                                    <small class="text-muted">Last updated 3 mins ago</small>
                                </p>
                            </div>
                        </div>
                        <div class="card mb-4">
                            <img class="card-img-top img-fluid" src="@/assets/images/small/img-6.jpg"
                                alt="Card image cap">
                            <div class="card-body">
                                <h4 class="card-title">Card title</h4>
                                <p class="card-text">This is a wider card with supporting text below as
                                    a natural lead-in to additional content. This card has even longer
                                    content than the first to show that equal height action.</p>
                                <p class="card-text">
                                    <small class="text-muted">Last updated 3 mins ago</small>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- end row -->

        <div class="row">
            <div class="col-sm-12">
                <h4 class="my-3">Cards Masonry</h4>

                <div class="row" data-masonry='{"percentPosition": true }'>
                    <div class="col-sm-6 col-lg-4">
                        <div class="card">
                            <img src="@/assets/images/small/img-3.jpg" class="card-img-top" alt="...">
                            <div class="card-body">
                                <h5 class="card-title">Card title that wraps to a new line</h5>
                                <p class="card-text">This is a longer card with supporting text below as a natural
                                    lead-in to
                                    additional content. This content is a little bit longer.</p>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-4">
                        <div class="card">
                            <img src="@/assets/images/small/img-5.jpg" class="card-img-top" alt="...">
                            <div class="card-body">
                                <h5 class="card-title">Card title</h5>
                                <p class="card-text">This card has supporting text below as a natural lead-in to
                                    additional
                                    content.</p>
                                <p class="card-text"><small class="text-muted">Last updated 3 mins ago</small></p>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-4">
                        <div class="card">
                            <img src="@/assets/images/small/img-7.jpg" class="card-img-top" alt="...">
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-4">
                        <div class="card p-3 text-end">
                            <blockquote class="blockquote blockquote-reverse  mb-0">
                                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer posuere erat a ante.
                                </p>
                                <footer class="blockquote-footer mt-0">
                                    <small class="text-muted">
                                        Someone famous in <cite title="Source Title">Source Title</cite>
                                    </small>
                                </footer>
                            </blockquote>
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <blockquote class="blockquote font-size-14 mb-0">
                                    <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer posuere erat a
                                        ante.</p>
                                    <footer class="blockquote-footer mt-0 font-size-12">
                                        Someone famous in <cite title="Source Title">Source Title</cite>
                                    </footer>
                                </blockquote>
                            </div>
                        </div>
                    </div>

                    <div class="col-sm-6 col-lg-4">
                        <div class="card bg-primary text-white text-center p-3">
                            <blockquote class="card-blockquote font-size-14 mb-0">
                                <p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Integer posuere erat.</p>
                                <footer class="blockquote-footer mt-0 text-white font-size-12 mb-0">
                                    Someone famous in <cite title="Source Title">Source Title</cite>
                                </footer>
                            </blockquote>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Card title</h5>
                                <p class="card-text">This is another card with title and supporting text below. This
                                    card has
                                    some additional content to make it slightly taller overall.</p>
                                <p class="card-text"><small class="text-muted">Last updated 3 mins ago</small></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-sm-6 col-lg-4">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Card title</h5>
                                <p class="card-text">This card has a regular title and short paragraphy of text below
                                    it.</p>
                                <p class="card-text"><small class="text-muted">Last updated 3 mins ago</small></p>
                            </div>
                        </div>
                    </div>

                </div>
                <!-- end row -->
            </div>
        </div>
        <!-- end row -->

    </Layout>
</template>
