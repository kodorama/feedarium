import axiosLib from 'axios';

const axios = axiosLib.create({
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        Accept: 'application/json',
    },
});

export default axios;

