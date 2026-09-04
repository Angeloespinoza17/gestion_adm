export const canUseLiveCamera = () => typeof navigator !== "undefined"
  && navigator.mediaDevices
  && typeof navigator.mediaDevices.getUserMedia === "function";

export const openRearCamera = () => navigator.mediaDevices.getUserMedia({
  video: { facingMode: { ideal: "environment" } },
  audio: false,
});

export const stopMediaStream = (stream) => {
  stream?.getTracks?.().forEach((track) => track.stop());
};

export const captureCameraPhoto = async (video, canvas, filenamePrefix = "foto") => {
  if (!video || !canvas || !video.videoWidth || !video.videoHeight) return null;

  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  const context = canvas.getContext("2d");
  if (!context) return null;
  context.drawImage(video, 0, 0, canvas.width, canvas.height);

  const blob = await new Promise((resolve) => canvas.toBlob(resolve, "image/jpeg", 0.9));
  if (!blob) return null;

  return new File([blob], `${filenamePrefix}-${Date.now()}.jpg`, {
    type: "image/jpeg",
    lastModified: Date.now(),
  });
};
