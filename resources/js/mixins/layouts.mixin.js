export default {
    data() {
        return {
            layout: {
                type: "vertical",
                sidebar: "dark",
                width: "fluid",
                topbar: "light",
                mode: "light",
                loader: false
            }
        };
    },
    // created() {
    //     if (!localStorage.getItem("layout")) {
    //         localStorage.setItem("layout", JSON.stringify(this.layout));
    //     }
    //     const layout = JSON.parse(localStorage.getItem("layout")) || {};
    //     if (layout) {
    //         this.layout = {
    //             ...layout,
    //             sidebar:
    //                 layout?.type === "horizontal"
    //                     ? layout?.topbar || this.layout.topbar
    //                     : layout?.sidebar || this.layout.sidebar
    //         };
    //     }
    // },
    created() {
        if (!localStorage.getItem("layout")) {
            localStorage.setItem("layout", JSON.stringify(this.layout));
        }
    
        const storedLayout = localStorage.getItem("layout");
    
        // Try to parse the value, and handle the error if it's invalid
        try {
            const layout = JSON.parse(storedLayout);
            if (layout) {
                this.layout = {
                    ...layout,
                    sidebar: layout?.type === "horizontal"
                        ? layout?.topbar || this.layout.topbar
                        : layout?.sidebar || this.layout.sidebar
                };
            }
        } catch (error) {
            console.error("Error parsing layout from localStorage", error);
    
            // If parsing fails, fallback to default layout
            this.layout = { ...this.layout };
    
            // Optionally, reset localStorage to avoid future errors
            localStorage.setItem("layout", JSON.stringify(this.layout));
        }
    },
    methods: {
        changeMode(mode) {
            switch (mode) {
                case "dark":
                    document.body.setAttribute('data-bs-theme', 'dark');
                    sessionStorage.setItem("is_visited", "dark");
                    break;
                default:
                    document.body.setAttribute('data-bs-theme', 'light');
                    sessionStorage.setItem("is_visited", "default");
                    break;
            }
        },
        changeLayout(change) {
            this.layout = {
                ...this.layout,
                ...change
            };
            localStorage.setItem("layout", JSON.stringify(this.layout));
        },
        changeSidebar(sidebar) {
            switch (sidebar) {
                case "dark":
                    document.body.setAttribute("data-sidebar", "dark");
                    document.body.removeAttribute("data-topbar");
                    document.body.removeAttribute("data-sidebar-size");
                    document.body.classList.remove("vertical-collpsed");
                    document.body.removeAttribute("data-layout-scrollable");
                    break;
                case "light":
                    document.body.setAttribute("data-topbar", "dark");
                    document.body.removeAttribute("data-sidebar");
                    document.body.removeAttribute("data-sidebar-size");
                    document.body.classList.remove("vertical-collpsed");
                    document.body.removeAttribute("data-layout-scrollable");
                    break;
                case "compact":
                    document.body.setAttribute("data-sidebar-size", "small");
                    document.body.setAttribute("data-sidebar", "dark");
                    document.body.classList.remove("vertical-collpsed");
                    document.body.removeAttribute("data-topbar", "dark");
                    break;
                case "icon":
                    document.body.setAttribute("data-keep-enlarged", "true");
                    document.body.classList.add("vertical-collpsed");
                    document.body.setAttribute("data-sidebar", "dark");
                    document.body.removeAttribute("data-topbar", "dark");
                    document.body.removeAttribute("data-layout-scrollable");
                    break;
                case "colored":
                    document.body.setAttribute("data-sidebar", "colored");
                    document.body.removeAttribute("data-keep-enlarged");
                    document.body.classList.remove("vertical-collpsed");
                    document.body.removeAttribute("data-sidebar-size");
                    document.body.removeAttribute("data-layout-scrollable");
                    break;
                case "scrollable":
                    document.body.setAttribute("data-layout-scrollable", "true");
                    document.body.removeAttribute("data-bs-theme");
                    break;
                default:
                    document.body.setAttribute("data-sidebar", "dark");
                    document.body.setAttribute("data-bs-theme", "light");
                    break;
            }
        },
        changeTopbar(topbar) {
            switch (topbar) {
                case "dark":
                    document.body.setAttribute("data-topbar", "dark");
                    document.body.removeAttribute("data-layout-scrollable");
                    break;
                case "light":
                    document.body.setAttribute("data-topbar", "light");
                    document.body.removeAttribute("data-layout-size", "boxed");
                    document.body.removeAttribute("data-layout-scrollable");
                    break;
                case "colored":
                    document.body.setAttribute("data-topbar", "colored");
                    document.body.removeAttribute("data-layout-size", "boxed");
                    document.body.removeAttribute("data-layout-scrollable");
                    break;
                case "scrollable":
                    document.body.setAttribute("data-layout-scrollable", "true");
                    document.body.removeAttribute("data-bs-theme");
                    break;
                default:
                    document.body.setAttribute("data-topbar", "dark");
                    break;
            }
        },
        changeWidth(width) {
            switch (width) {
                case "boxed":
                    document.body.setAttribute("data-layout-size", "boxed");
                    break;
                case "fluid":
                    document.body.setAttribute("data-bs-theme", "light");
                    document.body.removeAttribute("data-layout-size");
                    break;
                default:
                    document.body.setAttribute("data-bs-theme", "light");
                    break;
            }
        },
        loadRightCollapse() {
            // right bar 
            setTimeout(() => {
                var overlayElement = document.querySelector(".rightbar-overlay");
                if (overlayElement) {
                    overlayElement.addEventListener('click', function () {
                        if (document.body.getAttribute('class')) {
                            var rightBarEnabled = document.body.getAttribute('class');
                            if (rightBarEnabled == "right-bar-enabled") {
                                document.body.classList.remove('right-bar-enabled');
                            }
                        }
                    })
                }
            }, 1)
        }
    },
};
