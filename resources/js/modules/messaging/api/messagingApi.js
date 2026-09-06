import axios from "axios";
import {
  isMessagingAccessRevokedError,
  revokeMessagingAccess,
} from "../services/messagingAccess";

const base = "/api/messaging";

const guarded = (request, options = {}) =>
  request.catch((error) => {
    if (isMessagingAccessRevokedError(error, options)) {
      revokeMessagingAccess(error);
    }
    throw error;
  });

export default {
  config: () => guarded(axios.get(`${base}/config`), { accessProbe: true }),
  summary: () => guarded(axios.get(`${base}/summary`), { accessProbe: true }),
  conversations: (params = {}) => guarded(axios.get(`${base}/conversations`, { params })),
  conversation: (id) => guarded(axios.get(`${base}/conversations/${id}`)),
  messages: (id, params = {}) => guarded(axios.get(`${base}/conversations/${id}/messages`, { params })),
  message: (id) => guarded(axios.get(`${base}/messages/${id}`)),
  send: (id, payload) => guarded(axios.post(`${base}/conversations/${id}/messages`, payload)),
  direct: (userId) => guarded(axios.post(`${base}/conversations/direct`, { user_id: userId })),
  group: (payload) => guarded(axios.post(`${base}/conversations/group`, payload)),
  updateConversation: (id, payload) => guarded(axios.patch(`${base}/conversations/${id}`, payload)),
  participants: (id, params = {}) => guarded(axios.get(`${base}/conversations/${id}/participants`, { params })),
  addParticipants: (id, userIds) => guarded(axios.post(`${base}/conversations/${id}/participants`, { user_ids: userIds })),
  removeParticipant: (id, userId) => guarded(axios.delete(`${base}/conversations/${id}/participants/${userId}`)),
  updateParticipant: (id, userId, payload) => guarded(axios.patch(`${base}/conversations/${id}/participants/${userId}`, payload)),
  transferOwnership: (id, userId) => guarded(axios.post(`${base}/conversations/${id}/transfer-ownership`, { user_id: userId })),
  setLock: (id, locked) => guarded(locked ? axios.post(`${base}/conversations/${id}/lock`) : axios.delete(`${base}/conversations/${id}/lock`)),
  users: (query) => guarded(axios.get(`${base}/users/search`, { params: { query } })),
  read: (conversationId, messageId) => guarded(axios.post(`${base}/conversations/${conversationId}/read`, { through_message_id: messageId })),
  acknowledge: (messageId, comment) => guarded(axios.post(`${base}/messages/${messageId}/acknowledge`, { comment })),
  receipts: (messageId, params = {}) => guarded(axios.get(`${base}/messages/${messageId}/receipts`, { params })),
  upload: (file, onUploadProgress) => { const body = new FormData(); body.append("file", file); return guarded(axios.post(`${base}/uploads`, body, { onUploadProgress })); },
  removeUpload: (token) => guarded(axios.delete(`${base}/uploads/${token}`)),
  react: (messageId, reaction) => guarded(axios.post(`${base}/messages/${messageId}/reactions`, { reaction })),
  unreact: (messageId, reaction) => guarded(axios.delete(`${base}/messages/${messageId}/reactions/${encodeURIComponent(reaction)}`)),
};
