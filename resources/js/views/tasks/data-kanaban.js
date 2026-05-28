import { avatar1, avatar2, avatar4, avatar5, avatar6, avatar7, avatar8 } from "../../../images/users/data";

const upcomingTasks = [
    {
        id: 1,
        title: 'Topnav layout design',
        date: '14 Oct, 2019',
        task: 'Waiting',
        user: [avatar4, avatar5],
        budget: 145,
        groupId: 1
    },
    {
        id: 2,
        title: 'Create a New Landing UI',
        date: '15 Oct, 2019',
        task: 'Approved',
        user: [avatar6, avatar7],
        budget: 112,
        groupId: 1
    },
    {
        id: 3,
        title: 'Create a Skote Logo',
        date: '15 Oct, 2019',
        task: 'Waiting',
        user: [avatar5],
        budget: 86,
        groupId: 1
    },
];

const progressTasks = [
    {
        id: 4,
        title: 'Brand logo design',
        date: '12 Oct, 2019',
        task: 'Complete',
        user: [avatar6],
        budget: 132,
        groupId: 2
    },
    {
        id: 5,
        title: 'Create a Blog Template UI',
        date: '13 Oct, 2019',
        task: 'Pending',
        user: [avatar8, avatar1],
        budget: 103,
        groupId: 2
    },
    {
        id: 6,
        title: 'Skote Dashboard UI',
        date: '13 Oct, 2019',
        task: 'Complete',
        user: [avatar2],
        budget: 94,
        groupId: 2
    },
];

const completedTasks = [
    {
        id: 7,
        title: 'Redesign - Landing page',
        date: '10 Oct, 2019',
        task: 'Complete',
        user: [avatar4],
        budget: 145,
        groupId: 3
    },
    {
        id: 8,
        title: 'Multipurpose Landing',
        date: '09 Oct, 2019',
        task: 'Complete',
        user: [avatar6, avatar7],
        budget: 92,
        groupId: 3
    },
    {
        id: 9,
        title: 'Skote landing Psd',
        date: '15 Oct, 2019',
        task: 'Waiting',
        user: [avatar5],
        budget: 86,
        groupId: 3
    },
];

export { upcomingTasks, progressTasks, completedTasks };

