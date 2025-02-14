<script setup>
    import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
    import { Head } from '@inertiajs/vue3';
    import { ref, onMounted, watch, onBeforeUnmount } from 'vue';
    import { usePage } from '@inertiajs/vue3';
    import axios from 'axios';
    import Peer from 'peerjs';

    defineProps({
        auth: Object,
        users: Array,
    });

    const auth = usePage().props.auth;
    const users = usePage().props.users;
    const selectedUser = ref(null);
    const peer = new Peer();
    const peerCall = ref(null)
    const remoteVideo = ref(null);
    const localVideo = ref(null);
    const isCalling = ref(false);
    const localStream = ref(null)


    const callUser = () => {
        axios.post(`/video-call/request/${selectedUser.value.id}`, {peerId: peer.id});
        isCalling.value = true
        displayLocalVideo();
    }

    const endCall = () => {
        peerCall.value.close();
        localStream.value.getTracks().forEach(track => track.stop());
        remoteVideo.value = null
        localVideo.value = null
        isCalling.value = false
    }

    const displayLocalVideo = () => {
        navigator.mediaDevices.getUserMedia({ video: true, audio: true })
        .then((stream) => {
            localVideo.value.srcObject = stream;
            localStream.value = stream;
        })
        .catch((err) => {
        console.error('Error accessing media devices:', err);
        });
    }

    const setSelectedUser = (user) => {
        selectedUser.value = user;
    };


    const recipientAcceptCall = (e) => {

        // send signal that recipient accept the call
        axios.post(`/video-call/request/status/${e.user.fromUser.id}`, { peerId: peer.id, status: 'accept'});

        // stand by for callers connection
        peer.on('call', (call) => {
        // will be used when ending a call
        peerCall.value = call;
        // accept call if the caller is the one that you accepted
        if(e.user.peerId == call.peer) {
            // Prompt user to allow media devices
            navigator.mediaDevices.getUserMedia({ video: true, audio: true })
            .then((stream) => {

            // Answer the call with your stream
            call.answer(stream);
            // Listen for the caller's stream
            call.on('stream', (remoteStream) => {
                remoteVideo.value.srcObject = remoteStream
            });

            // caller end the call
            call.on('close', () => {
                endCall()
            });
            })
            .catch((err) => {
            console.error('Error accessing media devices:', err);
            });
        }

        });

    }

    const createConnection = (e) => {
        let receiverId = e.user.peerId
        navigator.mediaDevices.getUserMedia({ video: true, audio: true })
        .then((stream) => {
        // Initiate the call with the receiver's ID
        const call = peer.call(receiverId, stream);
        // will be used when ending a call
        peerCall.value = call;

        // Listen for the receiver's stream
        call.on('stream', (remoteStream) => {
            remoteVideo.value.srcObject = remoteStream
        });

        // receiver end the call
        call.on('close', () => {
            endCall()
        });
        })
        .catch((err) => {
        console.error('Error accessing media devices:', err);
        });
    }

    const connectWebSocket = () => {
        // request video call
        window.Echo.private(`video-call.${auth.user.id}`).listen('RequestVideoCall',  (e) => {
        selectedUser.value = e.user.fromUser;
        isCalling.value = true
        recipientAcceptCall(e)
        displayLocalVideo();

        });

        // video call request accepted
        window.Echo.private(`video-call.${auth.user.id}`).listen('RequestVideoCallStatus', (e) => {
        createConnection(e)
        });
    };

    onMounted(() => {
        connectWebSocket();
    });

    onBeforeUnmount(() => {
        window.Echo.leave(`video-call.${auth.user.id}`);
    });

</script>
<script>
    export default {
    data() {
        return {
        users: [], // List of contacts
        selectedUser: null, // Selected user for chat
        isCalling: false,
        messages: [], // Chat messages
        newMessage: '', // New message input
        currentUser: { id: 1, name: "You" }, // Placeholder for the current user
        };
    },
    methods: {
        setSelectedUser(user) {
        this.selectedUser = user;
        this.messages = []; // Load messages when user is selected (fetch from API if needed)
        },
        callUser() {
        this.isCalling = true;
        },
        endCall() {
        this.isCalling = false;
        },
        sendMessage() {
        if (this.newMessage.trim() !== '') {
            this.messages.push({ text: this.newMessage, senderId: this.currentUser.id });
            this.newMessage = '';
        }
        }
    }
    };
</script>


<template>
    <Head title="Contacts" />
    <AuthenticatedLayout>
        <div class="h-screen flex bg-gray-100 mx-auto max-w-7xl sm:px-6 lg:px-8" style="height: 90vh;">
            <!-- Sidebar -->
            <div class="w-1/4 bg-white border-r border-gray-200">
            <div class="p-4 bg-gray-100 font-bold text-lg border-b border-gray-200">
                Contacts
            </div>
            <div class="p-4 space-y-4">
                <!-- Contact List -->
                <div
                v-for="(user, key) in users"
                :key="key"
                @click="setSelectedUser(user)"
                :class="[
                    'flex items-center p-2 hover:bg-blue-500 hover:text-white rounded cursor-pointer',
                    user.id === selectedUser?.id ? 'bg-blue-500 text-white' : ''
                ]"
                >
                <div class="w-12 h-12 bg-blue-200 rounded-full"></div>
                <div class="ml-4">
                    <div class="font-semibold">{{ user.name }}</div>
                </div>
                </div>
            </div>
            </div>

            <!-- Contact Area -->
            <div class="flex flex-col w-3/4">
            <!-- No Conversation Selected -->
            <div
                v-if="!selectedUser"
                class="h-full flex justify-center items-center text-gray-800 font-bold"
            >
                Select Contact
            </div>

            <template v-else>
                <!-- Contact Header -->
                <div class="p-4 border-b border-gray-200 flex items-center justify-between">
                <div class="flex items-center">
                    <div class="w-12 h-12 bg-blue-200 rounded-full"></div>
                    <div class="ml-4">
                    <div class="font-bold">{{ selectedUser?.name }}</div>
                    </div>
                </div>
                <div>
                    <button v-if="!isCalling" @click="callUser" class="ml-4 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">Call</button>
                    <button v-if="isCalling" @click="endCall" class="ml-4 px-4 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600">End Call</button>
                </div>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-4 bg-gray-50 relative">
                <template v-if="isCalling">
                    <video id="remoteVideo" ref="remoteVideo" autoplay playsinline muted class="border-2 border-gray-800 w-full"></video>
                    <video id="localVideo" ref="localVideo" autoplay playsinline muted class="m-0 border-2 border-gray-800 absolute top-6 right-6 w-4/12" style="margin: 0;"></video>
                </template>
                <div v-if="!isCalling" class="h-full flex justify-center items-center text-gray-800 font-bold">
                    No Chat
                </div>
                </div>

                <!-- Chat Section -->
                <div class="flex flex-col bg-white p-4 border-t border-gray-200">
                <div class="overflow-y-auto max-h-60 space-y-2">
                    <div v-for="(message, index) in messages" :key="index" :class="{'text-right': message.senderId === currentUser.id}">
                    <div class="inline-block p-2 rounded-lg" :class="message.senderId === currentUser.id ? 'bg-blue-500 text-white' : 'bg-gray-200 text-gray-800'">
                        {{ message.text }}
                    </div>
                    </div>
                </div>
                <div class="mt-4 flex items-center">
                    <input v-model="newMessage" type="text" class="flex-1 border p-2 rounded-lg" placeholder="Type a message..." @keyup.enter="sendMessage" />
                    <button @click="sendMessage" class="ml-2 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">Send</button>
                </div>
                </div>
            </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

