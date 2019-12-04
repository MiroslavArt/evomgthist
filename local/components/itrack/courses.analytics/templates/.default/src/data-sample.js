let courses = [
    {
        name: 'Дарья Б.',
        dealId: 15736,
        sessions: [
            {
                name: 'Сессия 1',
                sections: [
                    {
                        name: 'Урок первый',
                        tasks: [
                            {
                                id: 167654,
                                completeTill: '2019-12-21',
                                completed: false,
                            },
                            {
                                id: 167484,
                                completeTill: '2019-10-30',
                                completed: true
                            },
                            ...{}
                        ]
                    },
                    ...{}
                ]
            },
            ...{}
        ]
    },
    ...{}
];

let payments = [
    {
        name: 'Дарья Б.',
        dealId: 15736,
        payments: [
            {
                name: 'За урок 1',
                sum: 43000,
                date: '2019-12-31',
                paid: true
            },
            {
                name: 'Для тех, кто не понял урок 1',
                sum: 145000,
                date: '2019-01-30',
                paid: false
            },
            ...{}
        ]
    },
    ...{}
];